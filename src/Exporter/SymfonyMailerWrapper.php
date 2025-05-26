<?php

namespace Tabula17\Satelles\Odf\Exporter;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Tabula17\Satelles\Odf\Exception\ExporterException;
use Throwable;

/**
 * Implements the MailSenderInterface to handle email sending through Symfony Mailer.
 */
class SymfonyMailerWrapper implements MailSenderInterface
{
    public Transport\TransportInterface $transport;
    public Email $mail;

    /**
     * @param string $dsn
     * @param string $sender
     * @param array $recipients
     * @param string $subject
     * @param string|null $bodyText
     * @param string|null $bodyHtml
     */
    public function __construct(string $dsn, string $sender, array $recipients, string $subject, ?string $bodyText = null, ?string $bodyHtml = null)
    {
        $this->transport = Transport::fromDsn($dsn);
        $this->mail = new Email();
        $this->mail->from($sender);
        $this->mail->to(...$recipients);
        $this->mail->subject($subject);
        if ($bodyText) {
            $this->mail->text($bodyText);
        }
        if ($bodyHtml) {
            $this->mail->html($bodyHtml);
        }
    }

    /**
     * Attaches a file to the email.
     *
     * @param string $path The file path to attach.
     * @param string|null $filename The optional filename to display for the attachment. If null, the original filename is used.
     * @return void
     */
    public function attach(string $path, ?string $filename): void
    {
        $this->mail->attachFromPath($path, $filename);
    }

    /**
     * Sends the mail using the specified transport.
     *
     * @return mixed The result of the mail sending operation, as returned by the transport.
     * @throws ExporterException
     */
    public function send(): mixed
    {
        try {
            $msg = $this->transport->send($this->mail);
            if(!$msg) {
                throw new ExporterException(ExporterException::SENDER_NO_RETURN);
            }
            return $msg->getMessageId();
        } catch (Throwable $e) {
            throw new ExporterException(sprintf(ExporterException::SENDER_ERROR, $e->getMessage()), 0, $e);
        }
    }
}