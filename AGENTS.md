# Engineering rules
All source, identifiers, comments, UI text, documentation, tests and commit messages must be English. German identifiers, comments and documentation are prohibited.
Preserve the modular monolith and dependency direction: domain → application ports ← infrastructure; presentation translates HTTP. Domain rules must not depend on Symfony, Doctrine or HTTP.
Enforce permissions on the backend; navigation is never an authorization boundary. Preserve single-use tokens, session revocation, CSRF, audit atomicity and last-administrator protection.
Run relevant tests and update the OpenAPI contract, generated types and documentation with behavior changes. Never claim unexecuted checks passed.
Do not introduce real carrier purchases, payments or AI recommendations in Phase 1. Never fabricate business metrics. Do not commit secrets or ZIP archives.

## Git workflow and commit standards
Review requirements, implement one coherent unit with tests and documentation, run relevant checks, inspect the diff, stage explicit paths and create an English Conventional Commit before continuing. Never blindly use `git add .`.
Never combine a whole phase into one commit. Use small, atomic commits with descriptive `feat`, `fix`, `refactor`, `test`, `docs`, `build`, `ci` or `chore` messages. Keep tests with their functionality where practical. Generic messages such as “update”, “changes”, “work” and “final version” are prohibited.
Never commit secrets, credentials, local environment files or build artifacts. Do not rewrite published history without explicit authorization. Do not fabricate tests or history. Incomplete intermediate commits require explicit documentation and a development branch; never knowingly commit broken functionality as complete.
Use descriptive feature branches and focused pull requests where supported. PRs describe what changed, why, verification and known limitations. Preserve readable portfolio-quality history.
