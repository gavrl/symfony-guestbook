<?php

namespace App\Notification;

use App\Entity\Comment;
use Symfony\Component\Notifier\Message\EmailMessage;
use Symfony\Component\Notifier\Notification\{EmailNotificationInterface, Notification};
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;

class CommentReviewNotification extends Notification implements EmailNotificationInterface
{
    private Comment $comment;

    public function __construct(Comment $comment)
    {
        $this->comment = $comment;

        parent::__construct('New comment posted');
    }

    /**
     * @param EmailRecipientInterface $recipient
     * @param string|null $transport
     * @return EmailMessage|null
     */
    public function asEmailMessage(EmailRecipientInterface $recipient, string $transport = null): ?EmailMessage
    {
        $message = EmailMessage::fromNotification($this, $recipient);
        $message->getMessage()
            ->htmlTemplate('emails/comment_notification.html.twig')
            ->context(['comment' => $this->comment]);

        return $message;
    }
}