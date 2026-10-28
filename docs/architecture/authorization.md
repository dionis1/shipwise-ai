# Authorization

Database tables roles, permissions, user_roles and role_permissions are the source of truth. Users may have multiple staff roles. Framework ROLE_STAFF separates staff from customers; PermissionVoter enforces explicit database permissions and default deny on every admin endpoint. Session permission data drives navigation but is not an authorization control.

SUPER_ADMIN has all delivered permissions. PLATFORM_ADMIN has overview, staff read/status operations, role/permission read and audit read. Department roles have their department permission only, except support also has limited customer lookup. AUDITOR has read-only audit access; CUSTOMER has no staff permissions. See role-matrix.md for exact defaults.

Invitations/role assignment and role-permission updates additionally require actual SUPER_ADMIN membership, even if a role is misconfigured to include a management permission. SUPER_ADMIN and CUSTOMER permission definitions are immutable. Unknown roles and permissions are rejected. Customers cannot be assigned as staff roles. Staff changes affecting Super Administrators require a Super Administrator. The last active Super Administrator cannot be suspended or demoted. Failed checks roll back changes and audit atomically.

Unauthenticated requests return 401, authenticated unauthorized requests 403. Domain invariant failures return 422. Customer profile operations obtain the target from the authenticated identity, never a client-supplied user ID. Limited support reads expose name, email, ID and status only. No payment or address data exists.
