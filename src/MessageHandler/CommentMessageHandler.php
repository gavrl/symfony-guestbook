<?php

namespace App\MessageHandler;

use App\ImageOptimizer;
use App\Message\CommentMessage;
use App\Notification\CommentReviewNotification;
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
use Symfony\Component\Notifier\NotifierInterface;
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

    /** @var NotifierInterface */
    private NotifierInterface $notifier;

    /** @var ImageOptimizer */
    private ImageOptimizer $imageOptimizer;

    /** @var string */
    private string $photoDir;

    public function __construct(
        EntityManagerInterface $entityManager,
        SpamChecker $spamChecker,
        CommentRepository $commentRepository,
        MessageBusInterface $bus,
        WorkflowInterface $commentStateMachine,
        NotifierInterface $notifier,
        ImageOptimizer $imageOptimizer,
        string $photoDir,
        LoggerInterface $logger = null
    ) {
        $this->entityManager = $entityManager;
        $this->spamChecker = $spamChecker;
        $this->commentRepository = $commentRepository;
        $this->bus = $bus;
        $this->workflow = $commentStateMachine;
        $this->notifier = $notifier;
        $this->imageOptimizer = $imageOptimizer;
        $this->photoDir = $photoDir;
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
            dump($score);
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
            $this->notifier->send(new CommentReviewNotification($comment), ...$this->notifier->getAdminRecipients());
        } elseif ($this->workflow->can($comment, 'optimize')) {
            if ($comment->getPhotoFilename()) {
                $this->imageOptimizer->resize($this->photoDir . '/' . $comment->getPhotoFilename());
            }
            $this->workflow->apply($comment, 'optimize');
            $this->entityManager->flush();
        } elseif ($this->logger) {
            $this->logger->debug(
                'Dropping comment message',
                ['comment' => $comment->getId(), 'state' => $comment->getState()]
            );
        }
    }
}