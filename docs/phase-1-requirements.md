# CODEX MASTER PROMPT — SHIPWISE AI — PHASE 1

## 1. Your Role and Mission

Act as a Principal Software Architect, Senior Symfony Backend Engineer, Senior Vue.js Frontend Engineer, DevSecOps Engineer, and QA Automation Engineer.

You are responsible for implementing Phase 1 of **ShipWise AI**, an enterprise-grade, AI-ready, multi-carrier shipping comparison and booking platform.

This is a real engineering portfolio project intended to demonstrate professional software engineering standards, architectural decision-making, security awareness, maintainability, automated testing, and AI-assisted development.

**Your task is to write and integrate the actual working code, not merely produce a plan.**

Inspect the existing repository before making changes. Preserve useful existing code and documentation. If the repository is empty, initialize the project from scratch.

### Mandatory Language Policy

ALL technical project content MUST be written in English.

This includes:

- PHP and TypeScript source code.
- Classes, interfaces, methods, variables, enums, namespaces, and database tables.
- Code comments and PHPDoc/TSDoc.
- API endpoints, request and response fields, error messages.
- Frontend components and user-facing UI text.
- Form validation messages and notifications.
- Database migrations and fixtures.
- Test names, assertions, and descriptions.
- README files, architecture documentation, ADRs, and OpenAPI specifications.
- Git commit messages, pull request descriptions, and CI workflow names.
- Agent instructions and development documentation.

**Never use German in the codebase.** English is the default product language. Design the frontend to support future localization, but do not implement translations in Phase 1.

Use consistent, descriptive naming. Avoid unnecessary abbreviations.

---

## 2. Product Context

ShipWise AI is a multi-carrier shipping marketplace.

Customers will eventually be able to:

1. Enter shipment information, including origin, destination, dimensions, and weight.
2. Compare shipping offers from carriers such as DHL, DPD, GLS, and UPS.
3. Select a suitable shipping offer.
4. Purchase the selected shipping service through ShipWise AI.
5. Receive a shipping label and tracking reference.
6. Track shipment progress.
7. Receive AI-assisted recommendations.

ShipWise AI acts as an intermediary and potentially a reseller. Carrier purchasing and commercial resale permissions depend on actual provider agreements.

The platform must distinguish:

- Carrier purchasing prices.
- Customer selling prices.
- Platform margins and fees.
- Customer orders and payments.
- Carrier shipment bookings.
- Customer ownership and staff access.

### Important Phase 1 Boundary

Phase 1 focuses on the production-oriented technical foundation, identity management, authorization, staff administration, frontend architecture, documentation, and automated tests.

**Do not implement real carrier purchases, live shipping quotations, payment capture, or AI recommendations during Phase 1.**

Prepare clean architectural boundaries for those future capabilities.

No real DHL, DPD, UPS, or GLS credentials should be required.

Do not present simulated prices, revenue, payments, or shipping activity as real business data.

---

## 3. Required Technology Stack

### Backend

- PHP 8.4.
- Symfony 7.4 LTS.
- Symfony Security.
- Symfony Validator.
- Symfony Messenger, configured for future background processing.
- Doctrine ORM.
- Doctrine Migrations.
- PostgreSQL.
- Redis where needed for caching, rate limiting, or session infrastructure.
- Symfony Mailer.
- OpenAPI 3.x.
- PHPUnit.
- PHPStan.
- PHP-CS-Fixer or another consistent code-style tool.

### Frontend

- Vue 3 with Composition API.
- TypeScript in strict mode.
- Vite.
- Tailwind CSS.
- Pinia.
- Vue Router.
- A maintainable, accessible UI component architecture.
- Vitest.
- Vue Test Utils.
- Playwright.
- OpenAPI-generated TypeScript API types.

### Infrastructure

- Docker and Docker Compose.
- Nginx or a suitable reverse proxy.
- PostgreSQL container.
- Redis container.
- Mailpit for local email testing.
- GitHub Actions.
- Environment-based configuration.
- Structured application logging.

Use stable, compatible dependency versions and commit all lockfiles.

Explain any major deviation from this stack in an Architecture Decision Record.

---

## 4. Architecture Requirements

Implement a **modular monolith** using Domain-Driven Design principles, Hexagonal Architecture, dependency inversion, and clean module boundaries.

Do not introduce distributed microservices in Phase 1.

### Required Architectural Layers

Each implemented business module should follow:

**Domain**
- Entities.
- Value objects.
- Domain rules.
- Domain-specific exceptions.
- Repository interfaces where appropriate.

**Application**
- Use cases.
- Commands and queries where they provide value.
- Application services.
- Request-independent business workflows.
- Transaction coordination.

**Infrastructure**
- Doctrine persistence implementations.
- Framework integrations.
- External service adapters.
- Mail and message implementations.

**Presentation**
- REST controllers.
- Request DTOs.
- Response DTOs.
- Validation.
- API serialization.

Business rules must not depend on HTTP controllers, Vue components, or Doctrine-specific implementation details.

Apply SOLID principles and use dependency injection.

Avoid unnecessary abstractions, excessive inheritance, overengineering, and generic service classes.

### Initial Implemented Modules

- Identity.
- Access Control.
- Staff Administration.
- Customer Accounts.
- Audit Logging.
- Shared Kernel.

### Future Module Boundaries

Document the planned interfaces and responsibilities for:

- Carriers.
- Shipping Quotes.
- Pricing.
- Orders.
- Payments.
- Fulfillment.
- Shipments.
- Tracking.
- Billing.
- Marketing.
- AI Recommendations.

Do not generate large collections of empty service classes just to simulate an enterprise architecture.

Use real boundaries for implemented functionality and concise design documentation for future modules.

---

## 5. Monorepo Structure

Use the following structure as the baseline:

```text
shipwise-ai/
├── apps/
│   ├── backend/
│   │   ├── config/
│   │   ├── migrations/
│   │   ├── public/
│   │   ├── src/
│   │   │   ├── Shared/
│   │   │   ├── Identity/
│   │   │   ├── AccessControl/
│   │   │   ├── Administration/
│   │   │   └── Audit/
│   │   ├── tests/
│   │   └── composer.json
│   └── frontend/
│       ├── src/
│       │   ├── app/
│       │   ├── api/
│       │   ├── components/
│       │   ├── features/
│       │   ├── layouts/
│       │   ├── router/
│       │   ├── stores/
│       │   ├── types/
│       │   └── views/
│       └── package.json
├── infra/
│   ├── docker/
│   └── nginx/
├── docs/
│   ├── architecture/
│   ├── adr/
│   ├── api/
│   └── security/
├── scripts/
├── .github/
│   └── workflows/
├── AGENTS.md
├── compose.yaml
├── Makefile
└── README.md
```

Adapt the internal module layout as necessary, but maintain clear boundaries and provide a documented folder structure.

---

# 6. Identity and Authentication

Implement a complete authentication foundation.

### Customer Authentication

Customers must be able to:

- Register an account.
- Verify their email address.
- Log in.
- Log out.
- Request a password reset.
- Reset their password using a time-limited token.
- View their own profile.
- Update permitted profile fields.
- Change their password.
- View the status of their account.

### Staff Authentication

Staff members must not be created through public registration.

Staff accounts must support:

- Secure invitation by an authorized administrator.
- Invitation acceptance.
- Account activation.
- Account suspension.
- Role assignment.
- Role revocation.
- Login and logout.
- Password reset.
- Session invalidation following sensitive account changes.

Provide a secure bootstrap CLI command for creating the first Super Administrator.

Do not commit any default production credentials or hardcoded passwords.

### Authentication Security

Implement:

- Secure, HTTP-only session cookies.
- Appropriate SameSite and Secure cookie settings.
- CSRF protection for cookie-authenticated state-changing requests.
- Session rotation after authentication.
- Password hashing using modern Symfony-supported password hashers.
- Authentication request throttling.
- Safe and consistent authentication error responses.
- Email verification for customer registration.
- Expiring, single-use password-reset tokens.
- Secure handling of staff invitation tokens.
- No authentication credentials in frontend localStorage.
- Protection against account enumeration where appropriate.

Prefer a same-origin deployment model with the frontend and API served through a reverse proxy.

Keep development CORS configuration restricted and explicit.

Design mandatory staff MFA as a security requirement before production deployment. If MFA cannot be fully implemented within Phase 1, explicitly mark production staff authentication as incomplete and document the remaining work. Never claim production readiness without it.

---

# 7. Enterprise Role-Based Access Control

Implement a real, database-backed RBAC system.

Do not use frontend navigation visibility as the only permission control.

Authorization must always be enforced by Symfony.

### Required Roles

**SUPER_ADMIN**

Full platform administration, including staff management, roles, permissions, platform settings, and audit access.

**PLATFORM_ADMIN**

Day-to-day platform administration and operational oversight, without automatic permission to modify all security-critical settings.

**OPERATIONS_MANAGER**

Future shipment operations, booking monitoring, carrier workflow supervision, and operational exception handling.

**QA_ENGINEER**

Access to the quality assurance dashboard, release/testing information, and permitted audit information. No unrestricted production data modification.

**FINANCE_MANAGER**

Access to finance-related sections, future transaction reconciliation, refunds, invoices, carrier costs, and financial reporting.

**MARKETING_MANAGER**

Access to marketing-specific sections, campaign administration, and approved aggregate analytics. No default access to sensitive payment or shipping-address data.

**CUSTOMER_SUPPORT**

Access to approved customer support workflows and minimal customer information needed for support. No payment-card or credential access.

**CARRIER_MANAGER**

Future carrier integration settings, provider configuration, and carrier health monitoring, subject to security restrictions.

**AUDITOR**

Read-only access to authorized audit and compliance information.

**CUSTOMER**

Access only to the customer's own account and, in future phases, their own quotes, orders, and shipments.

### Permission System

Use explicit permission identifiers such as:

```text
staff.read
staff.invite
staff.update
staff.suspend
roles.read
roles.assign
permissions.read
audit.read
platform.settings.read
platform.settings.update
qa.dashboard.view
finance.dashboard.view
marketing.dashboard.view
operations.dashboard.view
support.dashboard.view
carriers.dashboard.view
customers.read_limited
customers.update_limited
```

Future business permissions can include:

```text
quotes.read
quotes.create
orders.read
orders.manage
shipments.read
shipments.manage
payments.read
refunds.request
refunds.approve
pricing.manage
carriers.manage
marketing.campaigns.manage
```

Only implement permissions for functionality actually delivered in Phase 1. Reserve and document future permissions without granting access to nonexistent operations.

### Authorization Requirements

- Default deny.
- Explicit permission checking.
- Support multiple staff roles per user.
- Role assignment must be restricted.
- Prevent privilege escalation.
- Prevent staff from assigning permissions they are not authorized to manage.
- Protect the last active Super Administrator against accidental removal or deactivation.
- Use Symfony authorization voters where resource-level decisions require them.
- Return HTTP 401 for unauthenticated requests and HTTP 403 for authenticated but unauthorized requests.
- Ensure customers cannot access staff endpoints.
- Ensure Finance and Marketing cannot access each other's restricted information by default.
- Audit security-sensitive permission changes.

Create automated tests covering the authorization matrix.

---

# 8. Administration Platform

Build a professional, responsive administration platform in Vue 3.

All administrative interfaces must communicate with Symfony through REST APIs.

Use one shared administration layout with permission-aware navigation and distinct department dashboards.

### Global Admin Layout

Include:

- Responsive sidebar.
- Top navigation.
- Current user menu.
- Current role/permission-aware navigation.
- Logout action.
- Breadcrumbs.
- Search where supported.
- Loading states.
- Empty states.
- Error states.
- Success notifications.
- Accessible dialogs and form controls.
- Pagination for server-backed tables.
- Confirmation dialogs for destructive actions.

### 8.1 Super Administrator Dashboard

Route: `/admin`

Provide:

- Platform overview.
- Actual registered customer count.
- Actual active staff count.
- Account status overview.
- Recent security-relevant audit events.
- Staff management shortcuts.
- Platform health information safe for administrators.
- Administrative navigation.

Do not invent financial or shipping metrics.

### 8.2 Staff Management

Route: `/admin/staff`

Implement working functionality:

- List staff users.
- Search staff users.
- Filter by account status and role.
- View staff details.
- Invite new staff members.
- Assign permitted roles.
- Remove permitted roles.
- Suspend staff accounts.
- Reactivate staff accounts.
- View relevant staff audit history.

Sensitive operations require backend authorization.

### 8.3 Role and Permission Management

Route: `/admin/access-control`

Provide:

- Role listing.
- Role detail views.
- Permission listing.
- Role-permission matrix.
- Clear explanations of role capabilities.
- Protected assignment and update operations.

System-critical roles and permissions must not be freely editable without explicit authorization and safety checks.

### 8.4 QA Dashboard

Route: `/admin/qa`

Provide:

- QA-specific dashboard layout.
- Application version and environment identifier.
- Safe system health overview.
- Read-only deployment/build information where available.
- QA-related navigation.
- Clear placeholders for future test execution and bug-management integration.

Do not expose secrets, full environment variables, database credentials, or sensitive infrastructure details.

### 8.5 Finance Dashboard

Route: `/admin/finance`

Provide:

- Finance-specific layout.
- Restricted navigation.
- Clearly labeled empty states for future revenue, payments, expenses, refunds, and invoices.
- Consistent currency formatting utilities ready for future implementation.
- No fabricated financial results.

Actual financial transactions and reconciliation belong to later phases.

### 8.6 Marketing Dashboard

Route: `/admin/marketing`

Provide:

- Marketing-specific layout.
- Approved account-growth summary based on real, non-sensitive data if authorized.
- Future campaign and conversion sections.
- Privacy-conscious data presentation.
- No unauthorized access to customer addresses or financial information.

### 8.7 Operations Dashboard

Route: `/admin/operations`

Provide:

- Operations navigation and dashboard shell.
- Future shipments, carrier operations, and fulfillment monitoring sections.
- Accurate empty states before shipping features exist.

### 8.8 Customer Support Dashboard

Route: `/admin/support`

Provide:

- Support-specific layout.
- Permission-aware navigation.
- Limited customer lookup if the backend permission explicitly allows it.
- Appropriate protection of personally identifiable information.
- Future support case navigation.

### 8.9 Carrier Management Dashboard

Route: `/admin/carriers`

Provide:

- Carrier management dashboard shell.
- Future integration-status sections.
- Future provider configuration navigation.
- No real provider credentials or active integrations.

### 8.10 Audit Dashboard

Route: `/admin/audit`

Provide:

- Real audit event listing.
- Event type filtering.
- Actor filtering when authorized.
- Date-range filtering.
- Pagination.
- Detailed audit event views with sensitive fields redacted.

All dashboards must be protected by both frontend route guards and backend permissions.

For department capabilities not implemented in Phase 1, provide polished, honest empty states rather than fake working features.

---

# 9. Customer Application

Create the customer-facing application foundation.

Required routes:

```text
/
/login
/register
/verify-email
/forgot-password
/reset-password
/app
/app/profile
/app/settings
```

The public landing page should explain the future shipping comparison product without claiming currently unavailable capabilities.

The customer dashboard must include:

- A welcome section.
- Customer profile summary.
- Account verification status.
- Account settings navigation.
- A future shipping quote entry point clearly marked as unavailable in Phase 1.

Use reusable Vue components.

Keep the customer application separate from the staff administration experience.

---

# 10. REST API Requirements

Use a versioned JSON API under:

```text
/api/v1
```

Suggested endpoints:

```text
POST   /api/v1/auth/register
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
GET    /api/v1/auth/me
POST   /api/v1/auth/verify-email
POST   /api/v1/auth/password/forgot
POST   /api/v1/auth/password/reset

GET    /api/v1/me
PATCH  /api/v1/me
POST   /api/v1/me/password

GET    /api/v1/admin/dashboard
GET    /api/v1/admin/staff
POST   /api/v1/admin/staff/invitations
GET    /api/v1/admin/staff/{id}
PATCH  /api/v1/admin/staff/{id}
PUT    /api/v1/admin/staff/{id}/roles
GET    /api/v1/admin/roles
GET    /api/v1/admin/permissions
GET    /api/v1/admin/audit-events

GET    /api/v1/health/live
GET    /api/v1/health/ready
```

Add the remaining department endpoints only where they serve actual Phase 1 functionality.

### API Standards

- JSON request and response bodies.
- Consistent naming.
- Explicit input validation.
- Request and response DTOs.
- No direct exposure of Doctrine entities.
- Correct HTTP status codes.
- Structured error responses using an RFC 9457-compatible Problem Details format.
- Consistent pagination response format.
- Filtering and sorting allowlists.
- Correlation/request IDs.
- UTC timestamps using ISO 8601.
- No internal stack traces in production API responses.
- Explicit authorization.
- No mass-assignment vulnerabilities.

Generate a valid OpenAPI specification from the implemented API contract and keep it synchronized with the code.

Generate TypeScript API types or a typed client for the Vue application.

Avoid duplicate handwritten API models where generation is practical.

---

# 11. Database Architecture

Use PostgreSQL and Doctrine migrations.

At minimum, design and implement the schema needed for:

```text
users
roles
permissions
user_roles
role_permissions
staff_invitations
email_verification_tokens
password_reset_tokens
audit_events
```

Implement additional tables only where justified by real Phase 1 functionality.

### Database Requirements

- UUID identifiers where appropriate.
- Unique constraints.
- Foreign key constraints.
- Appropriate database indexes.
- Explicit nullable fields.
- Safe timestamps and UTC handling.
- Clearly defined account status values.
- Consistent naming conventions.
- Password-reset and invitation tokens stored securely.
- No plaintext passwords or credentials.
- Doctrine migrations committed to Git.

Use real relational integrity.

Prepare future domain documentation for customer organizations, carrier accounts, quotes, orders, payments, and shipments.

Do not implement premature shipping business tables unless required by a documented and tested architectural decision.

---

# 12. Audit Logging and Security

Implement actual audit logging for:

- Staff invitation creation.
- Staff activation and suspension.
- Role assignment and removal.
- Administrative permission changes.
- Sensitive account changes.
- Important authentication security events.

Audit records should include an appropriate actor identifier, action, target, timestamp, correlation ID, and sanitized context.

Avoid logging passwords, credentials, access tokens, full reset tokens, payment information, or unnecessary personal data.

Audit events should not be editable through ordinary application APIs.

### Additional Security Requirements

- Strict backend authorization.
- Input validation.
- Protection against SQL injection.
- XSS-conscious frontend rendering.
- Secure cookie configuration.
- CSRF protection.
- Login throttling.
- No hardcoded secrets.
- Environment-driven configuration.
- Separation of development and production settings.
- Appropriate security headers.
- Dependency vulnerability checks.
- Data minimization.
- Privacy-conscious logging.

Document GDPR-relevant considerations, including personal data retention, data access, and future deletion/anonymization workflows.

Do not falsely claim legal compliance without an actual compliance review.

---

# 13. Frontend Engineering Standards

Use Vue 3 Composition API with TypeScript.

Prefer `<script setup lang="ts">`.

Organize code by feature rather than placing all components into a single global folder.

Use:

- Pinia for appropriate shared state.
- Vue Router for navigation.
- Typed API services.
- Composables for reusable logic.
- Reusable forms and table components.
- Explicit loading, empty, error, and success states.
- Accessible form labels and keyboard navigation.
- Responsive layouts.
- Consistent spacing, typography, and component styling.

Use a professional SaaS administration design.

The UI should appear credible as a real business application, not as a demo generated from random UI templates.

Avoid unnecessary UI dependencies.

Do not hardcode authorization rules independently from the backend.

Retrieve current user permissions from the authenticated API session.

A hidden navigation item is not a security control.

---

# 14. Docker and Local Development

Provide a working local development environment.

The expected developer workflow should be close to:

```bash
git clone <repository-url>
cd shipwise-ai

cp .env.example .env

docker compose up -d --build

make setup
make migrate
make test
```

The exact commands may differ if necessary, but they must be documented and functional.

### Required Services

- Symfony backend.
- Vue frontend development server.
- PostgreSQL.
- Redis.
- Mailpit.
- Reverse proxy where appropriate.

### Requirements

- Application containers must communicate correctly.
- Use service names rather than hardcoded localhost addresses for container-to-container communication.
- Define appropriate health checks.
- Persist database data in a Docker volume.
- Avoid running application processes as root where practical.
- Provide usable environment examples.
- Do not commit secrets.
- Document local URLs and ports.
- Provide a clear database reset workflow for development/testing.
- Ensure frontend API requests work correctly through the configured proxy.

Do not require paid external services to run Phase 1 locally.

---

# 15. Automated Tests

A professional implementation must include real automated tests.

### Backend Unit Tests

Test:

- Account status rules.
- Permission evaluation.
- Role assignment rules.
- Security-critical domain behavior.
- Relevant application services.

### Backend Integration and Functional Tests

Test:

- Registration.
- Email verification.
- Login and logout.
- Password reset.
- Staff invitations.
- User profile operations.
- Protected administration endpoints.
- Authorized access.
- Unauthorized access.
- Permission escalation prevention.
- Audit event creation.
- Validation failures.
- Database persistence behavior.

### Frontend Tests

Use Vitest and Vue Test Utils to test:

- Authentication state.
- Route guards.
- Permission-aware navigation.
- Administrative components.
- Forms and validation.
- API error handling.

### End-to-End Tests

Use Playwright to cover:

1. Customer registration and login.
2. Customer profile access.
3. Staff login.
4. Super Administrator navigation.
5. Staff invitation and authorized role assignment.
6. Finance dashboard access by Finance Manager.
7. QA dashboard access by QA Engineer.
8. Rejected unauthorized administration access.
9. Logout and protected-route redirect.

Create reliable test fixtures.

Use isolated test accounts and databases.

Do not embed real secrets in tests.

---

# 16. Code Quality and CI/CD

Create GitHub Actions workflows that run on pull requests.

Backend checks:

- Composer validation.
- PHP coding standards.
- PHPStan static analysis.
- PHPUnit unit and integration tests.
- Symfony configuration validation.
- Doctrine migration validation where appropriate.

Frontend checks:

- Dependency installation using lockfiles.
- TypeScript type checking.
- ESLint.
- Unit tests.
- Production build.

Additional checks:

- Dependency security audits.
- API contract validation.
- Docker build checks.
- Playwright smoke tests where practical.

Configure jobs so failed tests or analysis produce a failed CI result.

Do not ignore failing tests to create the appearance of a green build.

Document required branch protection settings.

---

# 17. Documentation Requirements

Create high-quality English documentation.

### README.md

Include:

- Product overview.
- Technical stack.
- Architecture summary.
- Local setup instructions.
- Environment configuration.
- Available commands.
- Testing instructions.
- API documentation location.
- Phase 1 scope.
- Future roadmap.

### Architecture Documentation

Create:

```text
docs/architecture/overview.md
docs/architecture/backend.md
docs/architecture/frontend.md
docs/architecture/authentication.md
docs/architecture/authorization.md
docs/architecture/database.md
docs/architecture/api-contracts.md
docs/architecture/deployment.md
```

### Architecture Decision Records

Document decisions including:

- Why modular monolith instead of microservices.
- Why Symfony and Vue are separated by REST APIs.
- Authentication and session strategy.
- RBAC design.
- Database design.
- API versioning.
- Deployment and reverse proxy approach.

### AGENTS.md

Create an `AGENTS.md` file that instructs future AI coding agents to preserve the architecture, follow the code conventions, write everything in English, update tests, and maintain documentation.

AGENTS.md must explicitly prohibit introducing German identifiers, comments, or documentation.

---

# 18. Implementation Execution Strategy

Work incrementally and prioritize functional vertical slices.

### Step A — Repository Inspection

Inspect the repository and identify existing code, dependencies, and constraints.

Do not overwrite unrelated working files.

### Step B — Engineering Foundation

Set up the monorepo, Symfony, Vue, Docker, PostgreSQL, formatting tools, and environment configuration.

### Step C — Identity Foundation

Implement users, authentication, email verification, password reset, migrations, and tests.

### Step D — Authorization

Implement roles, permissions, permission checks, initial role definitions, secure bootstrap tooling, and tests.

### Step E — Administration APIs

Implement working staff administration, audit logging, administrative dashboard data, and tests.

### Step F — Frontend

Implement the public site, customer area, staff admin layout, department dashboards, and typed API integration.

### Step G — Quality and Documentation

Implement CI, E2E smoke tests, architecture documentation, and developer instructions.

### Step H — Verification

Run all available validation commands.

Fix identified defects.

Do not report unexecuted tests as passing.

If the environment cannot execute a verification step, report the exact reason and required command.

Do not stop after writing an implementation proposal. Continue implementing the defined Phase 1 scope.

Use small, coherent changes and meaningful conventional commit messages when committing is appropriate and supported.

---

# 19. Definition of Done

Phase 1 is complete only when all of the following are true:

- The project can start using the documented Docker workflow.
- Symfony successfully connects to PostgreSQL.
- Database migrations execute successfully.
- Vue communicates with Symfony through the REST API.
- A customer can register, verify their email, log in, and manage their profile.
- Authorized staff accounts can log in.
- The first Super Administrator can be securely created.
- Super Administrator can invite and manage staff.
- Roles and permissions are enforced on the backend.
- Department dashboards exist and are appropriately protected.
- Unauthorized users cannot access restricted administration data.
- Actual staff management actions are audited.
- The API is documented with OpenAPI.
- Frontend API types are synchronized with the API specification.
- Backend tests pass.
- Frontend tests pass.
- End-to-end smoke tests pass.
- Static analysis and linting pass.
- GitHub Actions workflows are defined.
- No credentials or secrets are committed.
- Technical code and documentation are written entirely in English.
- All incomplete features are clearly identified.
- The repository is ready for Phase 2 development without major architectural restructuring.

A dashboard with fake financial or shipping data does not count as a completed business feature.

An endpoint that bypasses authorization does not count as complete.

A feature without relevant tests does not satisfy the expected engineering quality.

---

# 20. Final Deliverables

After implementation, provide:

1. A concise summary of the implemented architecture.
2. The final repository structure.
3. A list of implemented backend modules.
4. A list of implemented frontend routes and dashboards.
5. The implemented roles and permissions.
6. All important local development commands.
7. Database migration instructions.
8. API documentation location.
9. Test results, clearly distinguishing executed and unexecuted checks.
10. Known limitations and remaining production-readiness tasks.
11. Recommended next tasks for Phase 2.

Do not describe features as implemented unless the corresponding code actually exists.

Do not invent test results.

Do not claim the application is production-ready solely because it starts locally.

## Final Engineering Principle

Build ShipWise AI as if a professional engineering team will maintain the platform for the next five years.

Prioritize security, modularity, predictable behavior, maintainability, automated testing, clear documentation, and the separation of business logic from infrastructure.

**Start by inspecting the repository. Then implement Phase 1 completely, verify the work, and report the actual results.**
