<?php

namespace App\Tests\Unit;

use App\Identity\Infrastructure\SymfonyAccountMailer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class LocalizedAccountMailerTest extends TestCase
{
    public static function copies(): iterable
    {
        yield ['en', 'verify', 'Verify your ShipWise email', '24 hours'];
        yield ['de', 'verify', 'Bestätige deine E-Mail-Adresse bei ShipWise', '24 Stunden'];
        yield ['fr', 'verify', 'Confirmez votre adresse e-mail ShipWise', '24 heures'];
        yield ['en', 'reset', 'Reset your ShipWise password', 'one hour'];
        yield ['de', 'reset', 'Setze dein ShipWise-Passwort zurück', 'einer Stunde'];
        yield ['fr', 'reset', 'Réinitialisez votre mot de passe ShipWise', 'une heure'];
        yield ['en', 'invite', 'You are invited to ShipWise', '48 hours'];
        yield ['de', 'invite', 'Du bist zu ShipWise eingeladen', '48 Stunden'];
        yield ['fr', 'invite', 'Vous êtes invité à rejoindre ShipWise', '48 heures'];
        yield ['unsupported', 'verify', 'Verify your ShipWise email', '24 hours'];
    }

    #[DataProvider('copies')]
    public function testSecureEmailUsesRecipientLocale(string $locale, string $purpose, string $subject, string $expiry): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->with(self::callback(function (Email $email) use ($subject, $expiry) {
            self::assertSame($subject, $email->getSubject());
            self::assertStringContainsString($expiry, $email->getTextBody());
            self::assertStringContainsString('#token='.str_repeat('a', 64), $email->getTextBody());
            self::assertStringNotContainsString('?token=', $email->getTextBody());

            return true;
        }));
        (new SymfonyAccountMailer($mailer, 'https://shipwise.test', 'accounts@shipwise.test', dirname(__DIR__, 2)))->send('recipient@example.test', $purpose, str_repeat('a',64), $locale);
    }
}
