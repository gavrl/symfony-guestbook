<?php

namespace App\MessageHandler;

use App\Message\CommentMessage;
use App\Repository\CommentRepository;
use App\Utils\SpamChecker;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Contracts\HttpClient\Exception\{ClientExceptionInterface,
    RedirectionExceptionInterface,
    ServerExceptionInterface,
    TransportExceptionInterface
};
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\WorkflowInterface;

class CommentMessageHandler implements MessageHandlerInterface
{
    /** @var SpamChecker */
    private SpamChecker $spamChecker;

    /** @var EntityManagerInterface */
    private EntityManagerInterface $entityManager;

    /** @var CommentRepository */
    private CommentRepository $commentRepository;

    /** @var MessageBusInterface */
    private MessageBusInterface $bus;

    /** @var LoggerInterface|null */
    private ?LoggerInterface $logger;

    /** @var WorkflowInterface */
    private WorkflowInterface $workflow;

    public function __construct(
        EntityManagerInterface $entityManager,
        SpamChecker $spamChecker,
        CommentRepository $commentRepository,
        MessageBusInterface $bus,
        WorkflowInterface $commentStateMachine,
        LoggerInterface $logger = null
    ) {
        $this->entityManager = $entityManager;
        $this->spamChecker = $spamChecker;
        $this->commentRepository = $commentRepository;
        $this->bus = $bus;
        $this->workflow = $commentStateMachine;
        $this->logger = $logger;
    }

    /**
     * @param CommentMessage $message
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function __invoke(CommentMessage $message)
    {
        $comment = $this->commentRepository->find($message->getId());
        if (!$comment) {
            return;
        }

        if ($this->workflow->can($comment, 'accept')) {
            $score = $this->spamChecker->getSpamScore($comment, $message->getContext());
            $transition = 'accept';
            if (2 === $score) {
                $transition = 'reject_spam';
            } elseif (1 === $score) {
                $transition = 'might_be_spam';
            }
            $this->workflow->apply($comment, $transition);
            $this->entityManager->flush();

            $this->bus->dispatch($message);
        } elseif (
            $this->workflow->can($comment, 'publish') ||
            $this->workflow->can($comment, 'publish_ham')
        ) {
            $this->workflow->apply($comment, $this->workflow->can($comment, 'publish') ? 'publish' : 'publish_ham');
            $this->entityManager->flush();
        } elseif ($this->logger) {
            $this->logger->debug(
                'Dropping comment message',
                ['comment' => $comment->getId(), 'state' => $comment->getState()]
            );
        }
    }
}