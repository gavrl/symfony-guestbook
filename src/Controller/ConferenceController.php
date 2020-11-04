<?php

namespace App\Controller;

use App\Entity\Conference;
use App\Repository\{CommentRepository, ConferenceRepository};
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
     * ConferenceController constructor.
     *
     * @param  Environment  $twig
     */
    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    /**
     * @Route("/", name="homepage")
     *
     * @param  ConferenceRepository  $conferenceRepository
     *
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function index(
      ConferenceRepository $conferenceRepository
    ): Response {
        return new Response(
          $this->twig->render(
            'conference/index.html.twig',
            [
              'conferences' => $conferenceRepository->findAll(),
            ]
          )
        );
    }

    /**
     * @Route("/conference/{id}", name="conference")
     *
     * @param  Request            $request
     * @param  Conference         $conference
     * @param  CommentRepository  $commentRepository
     *
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function show(
      Request $request,
      Conference $conference,
      CommentRepository $commentRepository
    ): Response {
        $offset    = max(0, $request->query->getInt('offset', 0));
        $paginator = $commentRepository->getCommentPaginator(
          $conference,
          $offset
        );

        dump($offset);

        return new Response(
          $this->twig->render(
            'conference/show.html.twig',
            [
              'conference' => $conference,
              'comments'   => $paginator,
              'previous'   => $offset - CommentRepository::PAGINATOR_PER_PAGE,
              'next'       => min(
                count($paginator),
                $offset + CommentRepository::PAGINATOR_PER_PAGE
              ),
            ]
          )
        );
    }

}
