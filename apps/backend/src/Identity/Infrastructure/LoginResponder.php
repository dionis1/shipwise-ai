<?php

namespace App\Identity\Infrastructure;

use App\Audit\Application\AuditRepository;
use App\Identity\Presentation\ProfileView;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final class LoginResponder implements AuthenticationSuccessHandlerInterface, AuthenticationFailureHandlerInterface, AuthenticationEntryPointInterface
{
    public function __construct(private ProfileView $profiles, private AuditRepository $audit)
    {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();
        if (!$user instanceof Principal) {
            throw new \LogicException('Unexpected principal.');
        }
        $this->audit->record($user->account->id(), 'auth.login', $user->account->id());

        return new JsonResponse($this->profiles->of($user->account));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $this->audit->record(null, 'auth.login_failed', null);

        return $this->problem('Invalid credentials or unavailable account.', 401);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return $this->problem('Authentication required.', 401);
    }

    private function problem(string $detail, int $status): JsonResponse
    {
        return new JsonResponse(['type' => 'about:blank', 'title' => 'Unauthorized', 'status' => $status, 'detail' => $detail], $status, ['Content-Type' => 'application/problem+json']);
    }
}
