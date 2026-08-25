# Blood Request and Compatibility Architecture

Phase 7B adds the recipient-side blood-bank workflow on top of the Phase 7A donor/component inventory foundation.

## Scope

- Patient blood requests are hospital and facility scoped and link to patient, clinician, optional encounter and optional admission.
- Request numbers, patient specimen labels and issue numbers use `NumberSequenceService`.
- Patient specimens keep unique labels and a JSON custody chain. Label discrepancies place the request in a hard-stop state.
- Patient ABO/Rh results are manually entered, independently verified and amended only through append-only amendment rows.
- Compatibility/crossmatch results are manually entered and independently authorized. The system records staff decisions; it does not calculate compatibility or clinical suitability.
- Reservations are transactional. A component is locked and moved from `available` to `reserved` during reservation, preventing double reservation.
- Issues record patient, request, component/unit, issuer, receiver, issue time and destination. Return and reversal preserve history.
- Emergency release requires explicit authorization and justification. It bypasses the manual compatibility authorization requirement only after audit capture.

## Data Model

- `blood_requests`: request header, counts, state, discrepancy flags and emergency-release authorization.
- `blood_request_specimens`: patient specimen identity, labels, custody and label discrepancy state.
- `patient_blood_groups`: manual ABO/Rh entries and independent verification.
- `patient_blood_group_amendments`: append-only corrections.
- `blood_compatibility_tests`: manual crossmatch/compatibility entries and authorization.
- `blood_component_reservations`: reservation status and expiry.
- `blood_component_issues`: issue, return and reversal lifecycle.

## Safety Boundaries

The workflow hard-stops when identity, specimen-label or blood-group discrepancies are unresolved. Component reservation is limited to released inventory in `available` state, matching the requested component type, not expired and not recalled. Compatibility and suitability are never inferred by software.

## Integration Points

Audit events are written to `blood_bank_events` and `audit_events`. Patient timeline events are written for request creation, state changes and issue events. Existing policies and hospital scoping protect patient and inventory records from cross-hospital access.
