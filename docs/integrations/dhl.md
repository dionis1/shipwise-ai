# DHL Parcel DE sandbox

Contract sources inspected on 2026-10-29: [Shipping API](https://developer.dhl.com/api-reference/parcel-de-shipping-post-parcel-germany-v2), [Shipping OpenAPI](https://developer.dhl.com/sites/default/files/2026-10/parcel-de-shipping-v2_4.yaml), [Authentication API](https://developer.dhl.com/api-reference/authentication-api-post-parcel-germany), [Authentication OpenAPI](https://developer.dhl.com/sites/default/files/2026-09/pp-account-auth-ropc.yaml).

The dedicated adapter uses the sandbox Authentication API password grant at `https://api-sandbox.dhl.com/parcel/de/account/auth/ropc/v1/token` with client ID, client secret, username and password. Cached bearer tokens expire 60 seconds before the documented `expires_in`, capped at 30 minutes. Expiry reacquires the password grant; no undocumented refresh-token grant is used. A rejected authenticated hello check can reacquire once.

A connection check calls the public shipping version root `https://api-sandbox.dhl.com/parcel/de/shipping/v2/`, then authenticated `/parcel/de/account/auth/ropc/v1/hello`. Version availability alone never establishes authenticated connectivity. Hello token metadata is discarded.

The only shipment operation is POST `/parcel/de/shipping/v2/orders?validate=true`. This documented validation mode does not create shipments or return labels. The adapter accepts no customer shipment input, uses a fixed synthetic domestic address, and requires an explicitly configured sandbox billing number and profile. Validation has no automatic retry. Any unexpected shipment number or label is rejected and discarded. A rejected fixture is reported as validation failure, without upstream address/error details. Successful validation does not imply rates, booking permission or production approval.

Set `DHL_SANDBOX_ENABLED=1` only in local development, with dedicated sandbox values for `DHL_CLIENT_ID`, `DHL_CLIENT_SECRET`, `DHL_USERNAME`, `DHL_PASSWORD`; `DHL_BILLING_NUMBER` and `DHL_PROFILE` additionally enable fixture validation. Keep all values in ignored runtime configuration. Defaults disable access. See [security boundary](security.md).

Verification: implemented and mock-tested only. No authenticated sandbox request has been executed and no provider approval or production readiness is claimed.
