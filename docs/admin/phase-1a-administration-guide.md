# Phase 1A Administration Guide

Date: 2026-08-14

## Available Administration Areas

- Hospital profile: legal identity, contacts, address, timezone, status, and currency.
- Facilities: branches, codes, primary facility, and active/inactive status.
- Departments: hospital-wide or facility-specific department records.
- Staff: user identity, staff profile, roles, facility memberships, and account status.
- Roles: permission matrix review and protected permission assignment.
- Settings: typed hospital settings for branding, locale, timezone, currency, formats, default facility, contacts, operating preferences, public-site defaults, and numbering preferences.
- Numbering: read-only sequence overview with backend-safe allocation/update support.
- Audit logs: read-only filterable event history.

## Staff Invitation

Administrators create staff with names, email, staff number, role assignments, and at least one facility. The system generates a random temporary password and sends a password-reset link so administrators do not choose or view permanent passwords.

Development installations commonly use log mail. Production must configure a real mail transport before invitations are used operationally.

## Authorization Notes

All administration routes are protected server-side. Hidden navigation is only a usability feature; policies and middleware enforce access. Non-superadministrators cannot assign `superadmin`, and the final active superadministrator cannot be suspended.

## Deferred

This phase does not implement patients, appointments, queues, encounters, billing, laboratory, radiology, pharmacy, inventory, admissions, blood bank, insurance/HMO, or public CMS editing.

