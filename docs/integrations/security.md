# Sandbox capability boundary

Location discovery and fixed-fixture shipment validation use separate ports from rate calculation. No sandbox adapter is registered as a rate provider. Existing development tariffs remain explicitly synthetic.

All operations use exact compiled HTTPS sandbox URLs, verified TLS, no redirects, an eight-second total deadline, four-second inactivity timeout and 256 KiB response limit. There are no automatic transport retries. Authentication rejection may trigger one token reacquisition for a safe lookup/check only. Validation is never retried. Exceptions expose stable reason codes without upstream messages, payloads or nested transport exceptions. The dedicated client is outside the Symfony HTTP profiler.

Tokens use a dedicated Redis cache pool and locked refresh. Redis must remain private to the backend network; do not expose it or include cache contents in artifacts. Production, test and E2E environments cannot enable provider networking through environment flags. Unit tests inject mock HTTP clients. Both an explicit development enable flag and complete credentials are required. Configuration examples contain empty placeholders only. Use ignored `.env.local` or managed runtime secrets; never put credentials in chat.

No public URL, operation, shipment body, billing identity or secret is accepted from customers. No shipment creation, booking, label storage, payments, protected GLS drop-off/pick-up operations or UPS integration is implemented.
