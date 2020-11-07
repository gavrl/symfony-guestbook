<?php

namespace App\Controller;

use App\Entity\Conference;
use App\Repository\{CommentRepository, ConferenceRepository};
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
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
     * ConferenceController constructor.
     *
     * @param Environment          $twig
     * @param ConferenceRepository $conferenceRepository
     * @param CommentRepository    $commentRepository
     */
    public function __construct(
        Environment $twig,
        ConferenceRepository $conferenceRepository,
        CommentRepository $commentRepository
    ) {
        $this->twig = $twig;
        $this->conferenceRepository = $conferenceRepository;
        $this->commentRepository = $commentRepository;
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
     * @param Request    $request
     * @param Conference $conference
     *
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function show(
        Request $request,
        Conference $conference
    ): Response {
        $offset = max(0, $request->query->getInt('offset', 0));
        $paginator = $this->commentRepository->getCommentPaginator(
            $conference,
            $offset
        );

        return new Response(
            $this->twig->render(
                'conference/show.html.twig',
                [
                    'conference'  => $conference,
                    'comments'    => $paginator,
                    'previous'    => $offset
                        - CommentRepository::PAGINATOR_PER_PAGE,
                    'next'        => min(
                        count($paginator),
                        $offset + CommentRepository::PAGINATOR_PER_PAGE
                    ),
                ]
            )
        );
    }

}
