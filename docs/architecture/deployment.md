# Deployment and development infrastructure

Compose connects Nginx to PHP-FPM for /api and Vue/Vite for SPA routes. Services use DNS names backend, frontend, database, redis and mailpit. Only loopback HTTP and Mailpit ports are published. PostgreSQL and Redis have health checks; backend checks dependencies are installed; frontend checks Vite. Development database has a named volume.

The provided Compose environment is for development. Production needs a separate immutable frontend build/static-serving image, backend dependency build, non-root processes, secret manager, TLS termination, trusted proxy configuration, Secure cookies, hardened CSP, shared session storage for horizontal instances, database least privilege, backups/restore drills and monitoring. Do not expose Vite or Mailpit publicly. Mandatory staff MFA is still a release blocker and APP_ENV=prod rejects staff login.

Use `docker compose exec backend php bin/console app:bootstrap-admin ...` interactively. Apply migrations as a release step after backups and before readiness. Destructive schema rollback is a local-only tool, not a production recovery strategy.

GitHub branch protection: require backend, frontend and e2e CI checks; require review and resolved conversations; block force pushes and deletion; require review from security owners for identity/access changes. CI definitions are committed, but remote protection and GitHub run results require repository administrator configuration and a pushed branch. An empty remote has no base branch for a PR until an initial branch is published.
