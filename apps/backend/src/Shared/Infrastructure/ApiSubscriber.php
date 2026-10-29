<?php

namespace App\Shared\Infrastructure;

use App\Shared\Domain\Identifier;
use App\Shared\Domain\RuleViolation;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class ApiSubscriber implements EventSubscriberInterface
{
    public function __construct(private CsrfTokenManagerInterface $csrf, private RateLimiterFactory $accountRequestsLimiter, private \Symfony\Component\Validator\Validator\ValidatorInterface $validator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return ['kernel.request' => [['identify', 256], ['protect', 16]], 'kernel.response' => 'response', 'kernel.exception' => ['exception', -64]];
    }

    public function identify(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $event->getRequest()->attributes->set('correlationId', Identifier::new());
        }
    }

    public function protect(RequestEvent $event): void
    {
        $r = $event->getRequest();
        if (!$event->isMainRequest() || !str_starts_with($r->getPathInfo(), '/api/')) {
            return;
        }
        if (!$r->isMethodSafe()) {
            if (!$this->csrf->isTokenValid(new CsrfToken('api', $r->headers->get('X-CSRF-Token', '')))) {
                throw new AccessDeniedHttpException('Invalid CSRF token.');
            }
            if ($r->getPathInfo() === '/api/v1/auth/login') {
                $input = \App\Shared\Presentation\Input::from($r, $this->validator, ['email', 'password']);
                $input->email();
                $input->string('password', 1, 128);
            }
            if (str_starts_with($r->getPathInfo(), '/api/v1/auth/') && !$this->accountRequestsLimiter->create($r->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
                throw new TooManyRequestsHttpException(900, 'Too many requests. Try again later.');
            }
        }
    }

    public function response(ResponseEvent $event): void
    {
        $r = $event->getRequest();
        $response = $event->getResponse();
        $response->headers->set('X-Request-ID', (string) $r->attributes->get('correlationId', ''));
        if (str_starts_with($r->getPathInfo(), '/api/')) {
            $response->headers->set('Cache-Control', 'no-store');
            $response->headers->set('X-Content-Type-Options', 'nosniff');
        }
    }

    public function exception(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }
        $error = $event->getThrowable();
        $status = $error instanceof HttpExceptionInterface ? $error->getStatusCode() : ($error instanceof RuleViolation ? 422 : ($error instanceof AccessDeniedException ? 403 : 500));
        $code = null;
        if ($error instanceof \App\Quoting\Domain\QuoteViolation) {
            $code = $error->reason;
            $status = match ($code) {
                'quote_not_found' => 404, 'quote_expired' => 410, 'idempotency_conflict' => 409, default => 422,
            };
        }
        if ($error instanceof \App\CarrierIntegration\Domain\IntegrationFailure) {
            $code = $error->reason;
            $status = 'search_invalid' === $code ? 422 : 409;
        }
        $titles = [400 => 'Bad Request', 401 => 'Unauthorized', 403 => 'Forbidden', 404 => 'Not Found', 405 => 'Method Not Allowed', 409 => 'Conflict', 422 => 'Unprocessable Content', 429 => 'Too Many Requests', 500 => 'Internal Server Error'];
        $event->setResponse(new JsonResponse(['code' => $code ?? \App\Shared\Presentation\ProblemCode::of($error->getMessage(), $status), 'type' => 'about:blank', 'title' => $titles[$status] ?? 'Request Failed', 'status' => $status, 'detail' => $status >= 500 ? 'The request could not be completed.' : $error->getMessage(), 'correlationId' => $event->getRequest()->attributes->get('correlationId')], $status, array_merge($error instanceof HttpExceptionInterface ? $error->getHeaders() : [], ['Content-Type' => 'application/problem+json'])));
    }
}
