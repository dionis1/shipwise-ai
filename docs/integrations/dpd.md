# DPD eSolutions sandbox

Primary contracts retrieved on 2026-10-29: [Login Service 2.0 WSDL](https://public-ws-stage.dpd.com/services/LoginService/V2_0/?wsdl), [Parcel Shop Finder 5.0 WSDL](https://public-ws-stage.dpd.com/services/ParcelShopFinderService/V5_0/?wsdl), [Finder XSD](https://public-ws-stage.dpd.com/services/ParcelShopFinderService/V5_0?xsd=ParcelShopFinderService-Public_5_0.xsd), [official manual](https://integrations.dpd.nl/wp-content/uploads/sites/5/2022/09/DPD-Shipper-Manual_WebServices.pdf). The [German documentation catalog](https://esolutions.dpd.com/entwickler/dpdwebservices/dokumentationen.aspx) requires a login for document access; the runtime contracts above were publicly available.

The SOAP 1.1 adapter posts `getAuth` to `https://public-ws-stage.dpd.com/services/LoginService/V2_0` with dedicated eSolutions `delisId`, password and message language. It never accepts unrelated DPD Cloud REST credentials. Login 2.0 supplies no expiry field: the token cache uses a conservative local five-minute TTL, without presenting it as provider expiry. A typed authentication fault or HTTP 401 during a lookup invalidates the token and retries once. A connection test explicitly reacquires authentication; it does not prove parcel-shop entitlement.

`findParcelShops` posts to `https://public-ws-stage.dpd.com/services/ParcelShopFinderService/V5_0` with the documented authentication SOAP header. This implementation sends country, postal code, optional city and limit only, omitting street, holidays and opening-time filters. It returns provider IDs, nullable address fields, supplied latitude/longitude and only service codes marked available. No inferred capabilities, opening hours, distances or shipping prices are returned.

XML parsing rejects DTDs/entities, disables external resolution, enforces the expected SOAP/operation namespaces and discards provider fault messages. WSDL/XSD files are never loaded dynamically at runtime. `storeOrders` is absent from the compiled endpoint/operation set.

Set `DPD_SANDBOX_ENABLED=1` in development with dedicated `DPD_DELIS_ID` and `DPD_PASSWORD` runtime secrets. Defaults disable access. See [security boundary](security.md).

Verification: public contract retrieval succeeded; mocked authentication and finder contracts are tested. No authenticated sandbox call, provider approval or production readiness is claimed.
