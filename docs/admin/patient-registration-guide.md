# Patient Registration Guide

Date: 2026-08-23

Use **Patients** in the admin navigation to register and manage identity records.

## Register a Patient

1. Select the registration facility.
2. Enter names, sex and either date of birth or estimated age.
3. Add address, phone, email, occupation and marital status when available.
4. Add optional identifiers. NIN is optional.
5. Add contacts and mark next of kin where appropriate.
6. Submit the form.

If possible duplicates are found, review the displayed hospital numbers and names. If the new registration is genuinely separate, tick the duplicate acknowledgement and submit again. Do not create merges in Phase 2A.

## Search

Search supports hospital number, name, exact phone number and exact approved identifier values. Phone, email and identifiers are matched through protected hashes, so partial phone or identifier searches are not supported.

## Patient Profile

The profile shows demographics, contacts, next of kin, identifiers, allergies, important alerts and the activity timeline. Demographic edits are audited. New identifiers and contacts may be appended; identity history should not be silently overwritten.

## Record State

Authorized users can mark a record active, archived or deceased with a required reason. Records are never hard-deleted.

## Allergies and Alerts

Allergies and alerts require authorized staff. Each entry records severity, status, notes, recorder and timestamps. Changes are audit logged and appear in the patient activity timeline foundation.

## Deferred

Patient photos and documents are not enabled until secure private storage, scanning and access logging are implemented. Appointments, encounters, billing, laboratory, pharmacy and admissions remain out of scope.
