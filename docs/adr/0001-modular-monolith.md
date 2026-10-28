# ADR 0001: Start with an API-first modular monolith

- **Status:** Accepted for initial architecture
- **Context:** The system needs several integrations and reliable cross-domain workflows, but an early-stage team should avoid distributed deployment and transactions across its own services.
- **Decision:** Implement bounded contexts in one Symfony codebase/deployment, backed by PostgreSQL and asynchronous Messenger workers. Vue is deployed separately and consumes JSON over a versioned REST API.
- **Rationale:** Fast feedback, strong transactional boundaries, simple local debugging and lower operational burden while preserving module interfaces.
- **Consequences:** Enforce architecture rules in tests and reviews; avoid shared domain-model access between modules. Independently scale worker and HTTP processes. Reassess service extraction only when supported by measured domain/team/scale constraints.
- **Alternatives rejected:** Microservices from day one; direct frontend-provider integration; feature-free CRUD-only MVC.
