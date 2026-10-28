<?php

namespace App\Identity\Presentation;

use App\Audit\Application\AuditRepository;
use App\Identity\Application\Accounts;
use App\Identity\Infrastructure\Principal;
use App\Shared\Presentation\Input;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1')]
final class AuthController extends AbstractController
{
    public function __construct(private Accounts $accounts, private ValidatorInterface $validator, private ProfileView $profiles)
    {
    }

    #[Route('/auth/csrf', methods: ['GET'])]
    public function csrf(CsrfTokenManagerInterface $csrf): JsonResponse
    {
        return $this->json(['token' => $csrf->getToken('api')->getValue()]);
    }

    #[Route('/auth/login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        return $this->json(['detail' => 'Invalid login request.'], 400);
    }

    #[Route('/auth/register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $input = Input::from($request, $this->validator, ['email', 'name', 'password']);
        $this->accounts->register($input->email(), $input->string('name', 2, 100), $input->string('password', 12, 128));

        return $this->json(['message' => 'If eligible, check your email to continue.'], 202);
    }

    #[Route('/auth/verify-email', methods: ['POST'])]
    public function verify(Request $request): JsonResponse
    {
        $input = Input::from($request, $this->validator, ['token']);
        $this->accounts->consume($input->string('token', 64, 64), 'verify');

        return $this->json(['message' => 'Email verified. You can now sign in.']);
    }

    #[Route('/auth/invitations/accept', methods: ['POST'])]
    public function accept(Request $request): JsonResponse
    {
        $input = Input::from($request, $this->validator, ['token', 'password']);
        $this->accounts->consume($input->string('token', 64, 64), 'invite', $input->string('password', 12, 128));

        return $this->json(['message' => 'Invitation accepted. You can now sign in.']);
    }

    #[Route('/auth/password/forgot', methods: ['POST'])]
    public function forgot(Request $request): JsonResponse
    {
        $input = Input::from($request, $this->validator, ['email']);
        $this->accounts->forgot($input->email());

        return $this->json(['message' => 'If eligible, check your email to continue.'], 202);
    }

    #[Route('/auth/password/reset', methods: ['POST'])]
    public function reset(Request $request): JsonResponse
    {
        $input = Input::from($request, $this->validator, ['token', 'password']);
        $this->accounts->consume($input->string('token', 64, 64), 'reset', $input->string('password', 12, 128));

        return $this->json(['message' => 'Password reset. You can now sign in.']);
    }

    #[Route('/auth/logout', methods: ['POST'])]
    public function logout(Request $request, Security $security, AuditRepository $audit): JsonResponse
    {
        $user = $this->getUser();
        if ($user instanceof Principal) {
            $audit->record($user->account->id(), 'auth.logout', $user->account->id());
            $security->logout(false);
        }
        $request->getSession()->invalidate();

        return $this->json(['message' => 'Signed out.']);
    }

    #[Route('/auth/me', methods: ['GET'])]
    #[Route('/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->json($this->profiles->of($this->principal()->account));
    }

    #[Route('/me', methods: ['PATCH'])]
    public function update(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $input = Input::from($request, $this->validator, ['name']);
        $user = $this->principal()->account;
        $this->accounts->profile($user, $input->string('name', 2, 100));

        return $this->json($this->profiles->of($user));
    }

    #[Route('/me/password', methods: ['POST'])]
    public function password(Request $request, Security $security): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $input = Input::from($request, $this->validator, ['currentPassword', 'password']);
        $this->accounts->password($this->principal()->account, $input->string('currentPassword', 1, 128), $input->string('password', 12, 128));
        $security->logout(false);
        $request->getSession()->invalidate();

        return $this->json(['message' => 'Password changed. Sign in again.']);
    }

    private function principal(): Principal
    {
        $user = $this->getUser();
        if (!$user instanceof Principal) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
