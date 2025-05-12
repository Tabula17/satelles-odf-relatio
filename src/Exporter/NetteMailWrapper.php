<?php

namespace Tabula17\Satelles\Odf\Exporter;

use Nette\Mail\Mailer;
use Nette\Mail\Message;

/**
 * The NetteMailWrapper class provides a wrapper around email construction and sending using a specified mail transport.
 * It supports setting the sender, recipients, subject, plain text body, and HTML body for an email.
 * Attachments can also be added, and the email can be sent via the configured mail transport.
 */
class NetteMailWrapper implements MailSenderInterface
{
    public Message $mail;
    public Mailer $transport;

    /**
     * @param Mailer $transport
     * @param string $sender
     * @param array $recipients
     * @param string $subject
     * @param string|null $bodyText
     * @param string|null $bodyHtml
     */
    public function __construct(Mailer $transport, string $sender, array $recipients, string $subject, ?string $bodyText = null, ?string $bodyHtml = null)
    {
        $this->transport = $transport;
        $this->mail = new Message();
        $this->mail->setFrom($sender);
        foreach ($recipients as $mail) {
            $this->mail->addTo($mail);
        }
        $this->mail->setSubject($subject);
        if ($bodyText) {
            $this->mail->setBody($bodyText);
        }
        if ($bodyHtml) {
            $this->mail->setHtmlBody($bodyHtml);
        }
    }

    /**
     * Attaches a file to the mail.
     *
     * @param string $path The file path of the attachment.
     * @param string|null $filename The display name for the attachment. If null, the file's original name will be used.
     * @return void
     */
    public function attach(string $path, ?string $filename): void
    {
        $this->mail->addAttachment($path, $filename);
    }

    /**
     * Sends the email using the configured transport.
     *
     * @return mixed Returns true on successful sending of the email.
     */
    public function send(): mixed
    {
        $this->transport->send($this->mail);
        return true;
    }
}