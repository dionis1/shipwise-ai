# Frontend

Vue 3 Composition API, strict TypeScript, Vue Router, Pinia, Vite and Tailwind CSS. Feature components handle team management, access control, audit and support lookup. Public, customer and staff experiences have separate page layouts. Feedback and pagination controls are reused. English is the product language; formatting helpers accept locale parameters for future localization.

The session store holds only the server-returned profile and permissions in memory. No credentials are stored in localStorage. Route metadata references permission IDs; the backend remains authoritative. Expired sessions are rejected by APIs even if a previously open navigation is stale. Form failures and network failures are shown explicitly.

OpenAPI-generated models are imported by the transport and feature views. The fetch transport obtains a fresh session-bound CSRF token before writes. Email tokens arrive via fragments, are read once and immediately removed from the browser URL.
