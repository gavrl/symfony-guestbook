<?php

namespace App\Notification;

use App\Entity\Comment;
use Symfony\Component\Notifier\Bridge\Telegram\Reply\Markup\Button\InlineKeyboardButton;
use Symfony\Component\Notifier\Bridge\Telegram\Reply\Markup\InlineKeyboardMarkup;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramOptions;
use Symfony\Component\Notifier\Message\{ChatMessage, EmailMessage};
use Symfony\Component\Notifier\Notification\{ChatNotificationInterface, EmailNotificationInterface, Notification};
use Symfony\Component\Notifier\Recipient\{EmailRecipientInterface, RecipientInterface};

class CommentReviewNotification extends Notification implements EmailNotificationInterface, ChatNotificationInterface
{
    /** @var Comment */
    private Comment $comment;

    /** @var string */
    private string $reviewUrl;

    /**
     * CommentReviewNotification constructor.
     * @param Comment $comment
     * @param string $reviewUrl
     */
    public function __construct(Comment $comment, string $reviewUrl)
    {
        $this->comment = $comment;
        $this->reviewUrl = $reviewUrl;
        parent::__construct('New comment posted');
    }

    /**
     * @param EmailRecipientInterface $recipient
     * @param string|null $transport
     * @return EmailMessage|null
     */
    public function asEmailMessage(EmailRecipientInterface $recipient, string $transport = null): ?EmailMessage
    {
        $message = EmailMessage::fromNotification($this, $recipient, $transport);
        $message->getMessage()
            ->htmlTemplate('emails/comment_notification.html.twig')
            ->context(['comment' => $this->comment]);

        return $message;
    }

    /**
     * @param RecipientInterface $recipient
     * @return string[]
     */
    public function getChannels(RecipientInterface $recipient): array
    {
        if (preg_match('{\b(great|awesome)\b}i', $this->comment->getText())) {
            return ['email', 'chat/telegram'];
        }

        $this->importance(Notification::IMPORTANCE_LOW);

        return ['email'];
    }

    /**
     * @param RecipientInterface $recipient
     * @param string|null $transport
     * @return ChatMessage|null
     */
    public function asChatMessage(RecipientInterface $recipient, string $transport = null): ?ChatMessage
    {
//        if ('telegram' !== $transport) {
//            return null;
//        }

        $message = ChatMessage::fromNotification($this, $recipient, $transport);
        $message->subject(
                sprintf(
                    "%s:\n%s (%s) says: %s",
                    $this->getSubject(),
                    $this->comment->getAuthor(),
                    $this->comment->getEmail(),
                    $this->comment->getText()
                )
        );

        $message->options(
            (new TelegramOptions())
                ->chatId('447416721')
                ->parseMode('HTML')
                ->disableWebPagePreview(true)
                ->disableNotification(true)
                ->replyMarkup(
                    (new InlineKeyboardMarkup())
                        ->inlineKeyboard(
                            [
                                (new InlineKeyboardButton('Accept'))
                                    ->url($this->reviewUrl),
                                (new InlineKeyboardButton('Reject'))
                                    ->url($this->reviewUrl . '?reject=1')
                            ]
                        )
                )
        );

        return $message;
    }
}