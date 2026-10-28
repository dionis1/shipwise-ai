# Authentication and sessions

Public registration always creates a pending customer and CUSTOMER role, never staff. Email verification activates customers. Staff are invited by a Super Administrator, then choose a password through a hashed single-use invitation valid for 48 hours. Verification expires after 24 hours; reset after 1 hour. Tokens have 256 bits of entropy and only SHA-256 hashes are persisted in separate relational tables. Resets only operate on active accounts and never reactivate suspended staff.

Symfony JSON login, automatic password hashing and a refreshed UserProvider handle authentication. The security principal compares account identity, active status and session version on each refresh. Password changes/resets, role changes, role-permission changes and suspension revoke previous sessions. Symfony migrates session IDs on authentication; logout invalidates the session. Profile updates allow names only; email/role/status injection is rejected.

HttpOnly, SameSite=Lax and auto-Secure cookies are used. Production requires TLS so Secure is enabled. CSRF tokens are required for every unsafe API method, including login and public account endpoints. All API responses disable caching. Redis backs rate limiting and distributed locks; reset/register requests use generic success responses. Login is limited to five attempts per identity/IP window plus a ten-request public account mutation window per IP. Test/E2E environments intentionally isolate or relax rate counters.

Mandatory staff MFA is missing. Production staff login is blocked in AccountChecker until enrollment, challenge, recovery, reset and audited enforcement exist. Password-only development staff access is not production-ready.
