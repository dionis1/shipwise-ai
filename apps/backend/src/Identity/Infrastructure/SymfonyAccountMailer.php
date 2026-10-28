<?php

namespace App\Identity\Infrastructure;

use App\Identity\Application\AccountMailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class SymfonyAccountMailer implements AccountMailer
{
    public function __construct(private MailerInterface $mailer, private string $appUrl, private string $mailFrom)
    {
    }

    public function send(string $email, string $purpose, string $token): void
    {
        $path = ['verify' => 'verify-email', 'reset' => 'reset-password', 'invite' => 'accept-invitation'][$purpose];
        // Fragments avoid leaking tokens in HTTP access logs and Referer headers.
        $link = rtrim($this->appUrl, '/').'/'.$path.'#token='.$token;
        $this->mailer->send((new Email())->from($this->mailFrom)->to($email)->subject('ShipWise: '.$purpose.' your account')->text("Continue securely using this single-use link:\n".$link."\nIf you did not request this, ignore this message."));
    }
}
