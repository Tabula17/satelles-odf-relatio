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
    public function __construct(string $dsn)
    {
        $this->transport = Transport::fromDsn($dsn);
        $this->mail = new Email();
       /* $this->mail->from($sender);
        $this->mail->to(...$recipients);
        $this->mail->subject($subject);
        if ($bodyText) {
            $this->mail->text($bodyText);
        }
        if ($bodyHtml) {
            $this->mail->html($bodyHtml);
        }*/
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

    public function setSubject(string $subject): void
    {
        $this->mail->subject($subject);
    }

    public function setBody(string $body, string $type = 'text'): void
    {
        if ($type === 'text') {
            $this->mail->text($body);
        } else {
            $this->mail->html($body);
        }
    }

    public function setTo(array $to): void
    {
        $this->mail->to(...$to);
    }

    public function setFrom(string $from): void
    {
        $this->mail->from($from);
    }

    public function setCc(array $cc): void
    {
        $this->mail->cc(...$cc);
    }

    public function setBcc(array $bcc): void
    {
        $this->mail->bcc(...$bcc);
    }
}