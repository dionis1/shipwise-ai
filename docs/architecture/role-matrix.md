# Default role matrix

Only delivered permissions are seeded. Role edits require `permissions.manage` AND membership of SUPER_ADMIN; SUPER_ADMIN and CUSTOMER permissions are immutable. Invitations and role assignment require SUPER_ADMIN in addition to endpoint permissions. PLATFORM_ADMIN can suspend/reactivate ordinary active staff, but cannot manage a Super Administrator.

## SUPER_ADMIN

Full security and staff administration

`platform.dashboard.view`, `staff.read`, `staff.invite`, `staff.suspend`, `roles.read`, `roles.assign`, `permissions.read`, `permissions.manage`, `audit.read`, `qa.dashboard.view`, `finance.dashboard.view`, `marketing.dashboard.view`, `operations.dashboard.view`, `support.dashboard.view`, `carriers.dashboard.view`, `customers.read_limited`

## PLATFORM_ADMIN

Daily account operations without security role management

`platform.dashboard.view`, `staff.read`, `staff.suspend`, `roles.read`, `permissions.read`, `audit.read`

## OPERATIONS_MANAGER

Shipment operations foundation

`operations.dashboard.view`

## QA_ENGINEER

Release and environment visibility

`qa.dashboard.view`

## FINANCE_MANAGER

Finance workspace

`finance.dashboard.view`

## MARKETING_MANAGER

Marketing workspace and aggregate growth

`marketing.dashboard.view`

## CUSTOMER_SUPPORT

Support workspace and limited customer lookup

`support.dashboard.view`, `customers.read_limited`

## CARRIER_MANAGER

Carrier workspace

`carriers.dashboard.view`

## AUDITOR

Read-only sanitized audit information

`audit.read`

## CUSTOMER

Own customer profile only

No staff permissions; own-profile access through authenticated identity.
