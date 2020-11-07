<?php

namespace App\Controller;

use App\Entity\{Comment, Conference};
use App\Form\CommentFormType;
use App\Repository\{CommentRepository, ConferenceRepository};
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\{File\File, Request, Response};
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;
use Twig\Error\{LoaderError, RuntimeError, SyntaxError};

class ConferenceController extends AbstractController
{

    /**
     * @var Environment
     */
    private Environment $twig;

    /**
     * @var ConferenceRepository
     */
    private ConferenceRepository $conferenceRepository;

    /**
     * @var CommentRepository
     */
    private CommentRepository $commentRepository;

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * ConferenceController constructor.
     *
     * @param Environment $twig
     * @param ConferenceRepository $conferenceRepository
     * @param CommentRepository $commentRepository
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        Environment $twig,
        ConferenceRepository $conferenceRepository,
        CommentRepository $commentRepository,
        EntityManagerInterface $entityManager
    )
    {
        $this->twig = $twig;
        $this->conferenceRepository = $conferenceRepository;
        $this->commentRepository = $commentRepository;
        $this->entityManager = $entityManager;
    }

    /**
     * @Route("/", name="homepage")
     *
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function index(): Response
    {
        return new Response(
            $this->twig->render(
                'conference/index.html.twig'
            )
        );
    }


    /**
     * @Route("/conference/{slug}", name="conference")
     *
     * @param Request $request
     * @param Conference $conference
     *
     * @param string $photoDir
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function show(
        Request $request,
        Conference $conference,
        string $photoDir
    ): Response
    {
        $comment = new Comment();
        $form = $this->createForm(CommentFormType::class, $comment);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $comment->setConference($conference);
            /** @var File $photo */
            if ($photo = $form->get('photo')->getData()) {
                $filename = bin2hex(random_bytes(6)).'.'.$photo->guessExtension();
                try {
                    $photo->move($photoDir, $filename);
                } catch (FileException $e) {
                    // unable to upload the photo, give up
                }
                $comment->setPhotoFilename($filename);
            }
            $this->entityManager->persist($comment);
            $this->entityManager->flush();

            return $this->redirectToRoute('conference', ['slug' => $conference->getSlug()]);
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
