# Appointments And Queues Architecture

Phase 2B adds scheduling, appointment intake, walk-ins, visits and queues. It does not add encounters, clinical notes, billing, laboratory, pharmacy, admissions or medical triage.

## Scope

- Appointment types define active booking durations per hospital.
- Clinician schedules define facility, optional department, day of week, working hours, breaks and active state.
- Clinician unavailability records leave or blocked periods with recorder, reason and timestamps.
- Appointments are hospital scoped and link patient, facility, optional department, clinician, type, source and status.
- Public appointment requests collect only name, phone or email, preferred facility/department/date and consent. They never create patients or confirmed appointments automatically.
- Visits are created at check-in for appointments and walk-ins as a foundation for later clinical phases.
- Queue entries are daily facility queues linked to visits and patients, with status, priority and queue number.

## Scheduling

Availability is generated from the hospital timezone, clinician schedule, breaks, unavailability and existing scheduled/confirmed/checked-in bookings. Booking and reschedule operations run in database transactions and check overlapping appointments with row locking before writing.

Appointment statuses are controlled through explicit actions:

- `scheduled`
- `confirmed`
- `cancelled`
- `no_show`
- `checked_in`

Every appointment write creates an `appointment_events` history row and an audit event.

## Public Request Intake

The public route is `/appointment/request`. It is rate limited, includes a honeypot field, requires consent and validates that the preferred facility and department are active. Phone and email values are encrypted for display and hashed for exact lookup. Staff must review each request and either accept or decline it; scheduling remains a staff workflow.

## Queue Flow

Check-in creates a visit and queue entry in one transaction. Queue numbers are allocated per facility and queue date. Queue actions are:

- `call`
- `recall`
- `transfer`
- `skip`
- `remove`
- `priority`

Priority changes require the `queues.prioritize` permission, a reason and audit logging. Queue actions keep full `queue_events` history.

## Security

Policies enforce hospital scope and permission checks. Patients with `archived` or `deceased` status cannot be booked or checked in in Phase 2B. Appointment and queue route-model access rejects records outside the current user's hospital to prevent IDOR. Public requests do not expose patient data and do not accept diagnoses or clinical details.

## Deferred

Medical triage, encounters, clinical documentation, billing, laboratory, pharmacy and admissions remain out of scope. Patient merging is still deferred.
