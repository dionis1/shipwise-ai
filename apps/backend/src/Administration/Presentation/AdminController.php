<?php

namespace App\Administration\Presentation;

use App\AccessControl\Application\AccessRepository;
use App\Administration\Application\StaffAdministration;
use App\Administration\Infrastructure\AdminQueries;
use App\Audit\Application\AuditRepository;
use App\Identity\Domain\UserRepository;
use App\Identity\Infrastructure\Principal;
use App\Identity\Presentation\ProfileView;
use App\Shared\Application\Transactions;
use App\Shared\Presentation\Input;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/admin')]
final class AdminController extends AbstractController
{
    public function __construct(private StaffAdministration $staff, private AdminQueries $queries, private AccessRepository $access, private AuditRepository $audit, private UserRepository $users, private ProfileView $profiles, private ValidatorInterface $validator, private Transactions $transactions, private string $version, private string $environment)
    {
    }

    #[Route('/dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        $this->denyAccessUnlessGranted('platform.dashboard.view');
        $data = $this->queries->overview();
        if ($this->isGranted('audit.read')) {
            $data['recentAudit'] = $this->audit->list([], 1)['items'];
        }$data['health'] = ['status' => 'available', 'version' => $this->version];

        return $this->json($data);
    }

    #[Route('/staff', methods: ['GET'])]
    public function list(Request $r): JsonResponse
    {
        $this->denyAccessUnlessGranted('staff.read');

        return $this->json($this->queries->staff($this->filters($r, ['search', 'status', 'role']), $this->page($r)));
    }

    #[Route('/staff/invitations', methods: ['POST'])]
    public function invite(Request $r): JsonResponse
    {
        $this->denyAccessUnlessGranted('staff.invite');
        $input = Input::from($r, $this->validator, ['email', 'name', 'roles']);
        $user = $this->staff->invite($this->actor(), $input->email(), $input->string('name', 2, 100), $input->strings('roles'));

        return $this->json($this->profiles->of($user), 201);
    }

    #[Route('/staff/{id}', methods: ['GET'])]
    public function detail(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('staff.read');
        $user = $this->target($id);
        $data = $this->profiles->of($user);
        if ($this->isGranted('audit.read')) {
            $data['audit'] = $this->audit->list(['target_id' => $id], 1);
        }

        return $this->json($data);
    }

    #[Route('/staff/{id}', methods: ['PATCH'])]
    public function status(Request $r, string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('staff.suspend');
        $input = Input::from($r, $this->validator, ['status']);
        $user = $this->target($id);
        $this->staff->status($this->actor(), $user, $input->string('status'));

        return $this->json($this->profiles->of($user));
    }

    #[Route('/staff/{id}/roles', methods: ['PUT'])]
    public function assign(Request $r, string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('roles.assign');
        $input = Input::from($r, $this->validator, ['roles']);
        $user = $this->target($id);
        $this->staff->roles($this->actor(), $user, $input->strings('roles'));

        return $this->json($this->profiles->of($user));
    }

    #[Route('/roles', methods: ['GET'])]
    public function roles(): JsonResponse
    {
        $this->denyAccessUnlessGranted('roles.read');

        return $this->json($this->access->matrix());
    }

    #[Route('/permissions', methods: ['GET'])]
    public function permissions(): JsonResponse
    {
        $this->denyAccessUnlessGranted('permissions.read');

        return $this->json($this->access->allPermissions());
    }

    #[Route('/roles/{role}/permissions', methods: ['PUT'])]
    public function rolePermissions(Request $r, string $role): JsonResponse
    {
        $this->denyAccessUnlessGranted('permissions.manage');
        if (!in_array('SUPER_ADMIN', $this->access->roles($this->actor()->id()), true)) {
            throw $this->createAccessDeniedException();
        }
        $input = Input::from($r, $this->validator, ['permissions']);
        $permissions = $input->strings('permissions');
        $this->transactions->run(function () use ($role, $permissions) {
            $this->access->updatePermissions($role, $permissions);
            $this->audit->record($this->actor()->id(), 'role.permissions_changed', null, ['roles' => $role]);
        });

        return $this->json(['message' => 'Permissions updated. Affected sessions have been revoked.']);
    }

    #[Route('/audit-events', methods: ['GET'])]
    public function audit(Request $r): JsonResponse
    {
        $this->denyAccessUnlessGranted('audit.read');
        $filters = $this->filters($r, ['action', 'actor_id', 'target_id', 'from', 'to']);
        foreach (['from', 'to'] as $key) {
            if ($filters[$key] === '') {
                continue;
            }
            $format = strlen($filters[$key]) === 10 ? 'Y-m-d' : 'Y-m-d\\TH:i:s\\Z';
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $filters[$key], new \DateTimeZone('UTC'));
            if (!$date || $date->format($format) !== $filters[$key]) {
                throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Use a valid ISO 8601 date or UTC timestamp filter.');
            }
            if ($key === 'to' && strlen($filters[$key]) === 10) {
                $date = $date->setTime(23, 59, 59);
            }
            $filters[$key] = $date->format('c');
        }

        return $this->json($this->audit->list($filters, $this->page($r)));
    }

    #[Route('/customers', methods: ['GET'])]
    public function customers(Request $r): JsonResponse
    {
        $this->denyAccessUnlessGranted('customers.read_limited');

        return $this->json($this->queries->customers(substr($r->query->getString('search'), 0, 180), $this->page($r)));
    }

    #[Route('/departments/{department}', methods: ['GET'])]
    public function department(string $department): JsonResponse
    {
        if (!in_array($department, ['qa', 'finance', 'marketing', 'operations', 'support', 'carriers'], true)) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted($department.'.dashboard.view');
        $data = ['department' => $department, 'available' => false, 'message' => 'Business workflows will be introduced in a future phase.'];
        if ($department === 'qa') {
            $data['version'] = $this->version;
            $data['environment'] = $this->environment;
            $data['health'] = 'available';
        }
        if ($department === 'marketing') {
            $data['customerCount'] = $this->queries->overview()['customerCount'];
        }

        return $this->json($data);
    }

    private function actor(): \App\Identity\Domain\User
    {
        $user = $this->getUser();
        if (!$user instanceof Principal) {
            throw $this->createAccessDeniedException();
        }

        return $user->account;
    }

    private function target(string $id): \App\Identity\Domain\User
    {
        $user = $this->users->byId($id);
        if (!$user || $user->kind() !== 'staff') {
            throw $this->createNotFoundException('Staff account not found.');
        }

        return $user;
    }

    private function page(Request $r): int
    {
        return max(1, min(100000, $r->query->getInt('page', 1)));
    }

    /** @param list<string> $keys
     * @return array<string,string> */
    private function filters(Request $r, array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = substr($r->query->getString($key), 0, 180);
        }

        return $result;
    }
}
