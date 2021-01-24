<?php

namespace App\Controller;

use App\Message\CommentMessage;
use App\Entity\{Comment, Conference};
use App\Form\CommentFormType;
use App\Repository\{CommentRepository, ConferenceRepository};
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\{File\File, Request, Response};
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Error\{LoaderError, RuntimeError, SyntaxError};

class ConferenceController extends AbstractController
{

    private Environment $twig;

    private ConferenceRepository $conferenceRepository;

    private CommentRepository $commentRepository;

    private EntityManagerInterface $entityManager;

    private MessageBusInterface $bus;

    /**
     * ConferenceController constructor.
     *
     * @param Environment $twig
     * @param ConferenceRepository $conferenceRepository
     * @param CommentRepository $commentRepository
     * @param EntityManagerInterface $entityManager
     * @param MessageBusInterface $bus
     */
    public function __construct(
        Environment $twig,
        ConferenceRepository $conferenceRepository,
        CommentRepository $commentRepository,
        EntityManagerInterface $entityManager,
        MessageBusInterface $bus
    ) {
        $this->twig = $twig;
        $this->conferenceRepository = $conferenceRepository;
        $this->commentRepository = $commentRepository;
        $this->entityManager = $entityManager;
        $this->bus = $bus;
    }

    /**
     * @Route("/", name="homepage")
     *
     * @param ConferenceRepository $conferenceRepository
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function index(ConferenceRepository $conferenceRepository): Response
    {
        $response = new Response(
            $this->twig->render(
                'conference/index.html.twig',
                [
                    'conferences' => $conferenceRepository->findAll(),
                ]
            )
        );
        $response->setSharedMaxAge(3600);

        return $response;
    }

    /**
     * @Route("/conference_header", name="conference_header")
     * @param ConferenceRepository $conferenceRepository
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function conferenceHeader(ConferenceRepository $conferenceRepository): Response
    {
        $response = new Response(
            $this->twig->render(
                'conference/header.html.twig',
                [
                    'conferences' => $conferenceRepository->findAll(),
                ]
            )
        );
        $response->setSharedMaxAge(3600);

        return $response;
    }

    /**
     * @Route("/conference/{slug}", name="conference")
     *
     * @param Request $request
     * @param Conference $conference
     * @param NotifierInterface $notifier
     * @param string
     *
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function show(
        Request $request,
        Conference $conference,
        NotifierInterface $notifier,
        string $photoDir
    ): Response {
        $comment = new Comment();
        $form = $this->createForm(CommentFormType::class, $comment);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $comment->setConference($conference);
            /** @var File $photo */
            if ($photo = $form->get('photo')->getData()) {
                $filename = bin2hex(random_bytes(6)) . '.' . $photo->guessExtension();
                try {
                    $photo->move($photoDir, $filename);
                } catch (FileException $e) {
                    // unable to upload the photo, give up
                }
                $comment->setPhotoFilename($filename);
            }
            $this->entityManager->persist($comment);
            $this->entityManager->flush();

            $context = [
                'user_ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('user-agent'),
                'referrer' => $request->headers->get('referer'),
                'permalink' => $request->getUri(),
            ];

            $reviewUrl = $this->generateUrl('review_comment', ['id' => $comment->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
            $this->bus->dispatch(new CommentMessage($comment->getId(), $reviewUrl, $context));

            $notifier->send(new Notification('Thank you for the feedback; your comment will be posted after moderation.', ['browser']));

            return $this->redirectToRoute('conference', ['slug' => $conference->getSlug()]);
        }

        if ($form->isSubmitted()) {
            $notifier->send(new Notification('Can you check your submission? There are some problems with it.', ['browser']));
        }

        $offset = max(0, $request->query->getInt('offset', 0));
        $paginator = $this->commentRepository->getCommentPaginator(
            $conference,
            $offset
        );

        return new Response(
            $this->twig->render(
                'conference/show.html.twig',
                [
                    'conference' => $conference,
                    'comments' => $paginator,
                    'previous' => $offset
                        - CommentRepository::PAGINATOR_PER_PAGE,
                    'next' => min(
                        count($paginator),
                        $offset + CommentRepository::PAGINATOR_PER_PAGE
                    ),
                    'comment_form' => $form->createView(),
                ]
            )
        );
    }

}
