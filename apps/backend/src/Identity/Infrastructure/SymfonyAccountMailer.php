<?php

namespace App\Identity\Infrastructure;

use App\Identity\Application\AccountMailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class SymfonyAccountMailer implements AccountMailer
{
    public function __construct(private MailerInterface $mailer, private string $appUrl, private string $mailFrom, private string $projectDir)
    {
    }

    public function send(string $email, string $purpose, string $token, string $locale = 'en'): void
    {
        $locale = in_array($locale, ['en', 'de', 'fr'], true) ? $locale : 'en';
        /** @var array<string, array<string,string>> $messages */
        $messages = json_decode(file_get_contents($this->projectDir.'/translations/account.'.$locale.'.json') ?: '', true, 512, JSON_THROW_ON_ERROR);
        $path = ['verify' => 'verify-email', 'reset' => 'reset-password', 'invite' => 'accept-invitation'][$purpose];
        // Keep high-entropy, single-use tokens in fragments, away from request logs and Referer headers.
        $link = rtrim($this->appUrl, '/').'/'.$path.'#token='.$token;
        $copy = $messages[$purpose];
        $this->mailer->send((new Email())->from($this->mailFrom)->to($email)->subject($copy['subject'])->text($copy['intro']."\n\n".$link."\n\n".$copy['expiry']."\n".$copy['ignore']));
    }
}
