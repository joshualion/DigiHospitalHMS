# Appointments And Queue Guide

Phase 2B gives reception, clinical and admin staff a controlled operational workflow for appointment requests, staff booking, check-in, walk-ins and daily queues.

## Permissions

- `appointments.view`: view calendars, lists and schedules.
- `appointments.book`: create appointments and walk-ins.
- `appointments.manage`: confirm, reschedule, cancel, mark no-show and check in.
- `appointment-requests.review`: accept or decline public appointment requests.
- `queues.view`: view queue boards.
- `queues.manage`: call, recall, transfer, skip and remove queue entries.
- `queues.prioritize`: change queue priority with a reason.

## Working Schedules

Add clinician working schedules from the appointment admin area. A schedule must belong to a facility and clinician and can optionally belong to a department. Breaks are stored on the schedule and are excluded from generated availability. Use unavailability records for leave, blocked time or temporary facility assignment gaps.

## Booking Appointments

Use `Appointments` in the sidebar. Select an active patient, facility, department, clinician, appointment type and start time. The system calculates the end time from the appointment type and rejects overlapping bookings for the clinician. Archived and deceased patients are blocked in this phase.

## Public Requests

Visitors use `/appointment/request` from the public CTA. Requests contain only contact and preference fields. Staff review the request, match or register a patient if appropriate, then schedule or decline manually. Public requests never create patients or confirmed appointments by themselves.

## Check-In And Walk-Ins

Checking in an appointment creates a visit and adds the patient to the daily queue. Walk-ins use an existing patient record and also create a visit and queue entry. Queue numbers are per facility per day.

## Queue Board

Use `Queues` in the sidebar to manage daily waiting patients. Staff can call, recall, skip, transfer or remove entries with reasons where required. Priority changes require the priority permission and are audited.

## Audit And History

Appointment changes write appointment history and audit events. Queue actions write queue history and audit events. Records are not hard deleted or silently overwritten.
