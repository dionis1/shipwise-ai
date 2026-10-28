# Architecture overview

Phase 1 is a modular monolith. The authoritative implementation scope is `docs/phase-1-requirements.md`; `docs/ARCHITECTURE.md` preserves the supplied long-term architecture and is not a claim that later shipping/payment modules exist.

Identity owns customer and staff account rules. AccessControl owns relational roles and permission evaluation. Administration coordinates privileged staff actions and read models. Audit records sanitized events. Shared provides IDs, domain failures, HTTP validation and transaction coordination. Customer Accounts are part of Identity until a real organization/account boundary justifies extraction.

Dependencies flow from presentation to application and domain. Application ports are bound to infrastructure adapters in Symfony DI. No Doctrine attributes or HTTP abstractions occur in the domain. Avoid premature empty modules.

Future contexts: Carriers owns capabilities and provider contracts; Shipping Quotes owns eligibility/provenance/expiry; Pricing owns carrier costs versus customer prices and integer minor units; Orders owns immutable accepted offers; Payments owns PSP events/refunds; Fulfillment owns idempotent booking/reconciliation; Shipments owns labels and ownership; Tracking owns physical milestones; Billing owns invoices; Marketing owns approved aggregates/campaigns; AI Recommendations provides advice from authoritative inputs. Future calls use application interfaces and transactional events, not another module’s ORM repository.
