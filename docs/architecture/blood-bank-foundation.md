# Blood Bank Foundation

Phase 7A introduces a hospital-scoped blood-bank foundation for donor registration, donation collection, manual testing clearance, component preparation and component inventory custody.

## Scope

- Blood-bank locations and storage units are scoped to hospital and facility.
- Donor contact, address and identifier values are encrypted; phone, email and identifier lookup use keyed hashes.
- Donor eligibility is never inferred by the system. Authorized staff manually record screening responses, eligibility decisions and reasons.
- Donations receive separate donation and collection/bag numbers through `NumberSequenceService`.
- Blood group and Rh results are entered, verified by a separate authorized user and corrected only through append-only amendments.
- Screening tests are administrator-configured and may reference existing laboratory catalogue tests.
- Components remain quarantined until required screening results are verified and explicitly marked cleared, and a verified blood group exists.
- Component lifecycle events are append-only through blood-bank events and application audit events.

## Component States

Supported states are `collected`, `processing`, `quarantined`, `available`, `reserved`, `issued`, `transferred`, `expired`, `recalled`, `discarded` and `consumed`.

Phase 7A implements preparation, quarantine release, transfer, recall, discard and reporting. Patient requests, compatibility decisions, issue and bedside transfusion are intentionally deferred.

## Safety Boundary

The application does not define donor eligibility rules, screening criteria, blood compatibility, clinical suitability or transfusion recommendations. Those decisions require local blood-bank professional validation and are recorded manually.
