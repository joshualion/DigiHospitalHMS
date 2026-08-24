# Admission Discharge Transfer Guide

Phase 6A supports administrative and clinical coordination for inpatient admission, bed allocation, transfers and discharge.

## Setup

1. Create bed classes and optionally map them to billable accommodation services.
2. Create wards for each facility and department.
3. Create rooms where needed.
4. Create beds and keep their states current.

## Admission Flow

1. Request admission from a patient, visit or encounter context.
2. Review and approve or reject the request.
3. Allocate an available or reserved bed.
4. Transfer the patient internally when a different bed, ward, department or facility is needed.
5. Discharge the patient when clinical and administrative requirements are complete.

## Bed States

- `available`: can be assigned.
- `reserved`: temporarily held and can be assigned.
- `occupied`: assigned to an active admission.
- `cleaning`: awaiting turnaround after transfer or discharge.
- `maintenance`, `blocked`, `inactive`: unavailable for admission.

Occupied beds cannot be manually released. The admission workflow must transfer or discharge the patient first.

## Discharge Clearance

Admissions can require administrative clearance before discharge. If unresolved, discharge is blocked unless an authorized override is entered with a reason. Override use is audited.

## Billing

Accommodation charges are generated at discharge from the occupancy movement history and the mapped bed-class service. Staff should review and issue the resulting invoice through the billing workflow.
