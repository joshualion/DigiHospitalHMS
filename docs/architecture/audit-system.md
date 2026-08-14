# Audit System

Date: 2026-08-14

## Approach

Phase 1A introduces an explicit domain audit service at `app/Services/AuditService.php` and immutable audit records in `audit_events`.

The audit service is intentionally callable from controllers and future service/actions. It is not only a generic observer, because future safety-critical workflows must decide precisely which business event occurred.

## Captured Fields

Audit events capture:

- hospital and optional facility
- actor
- action
- subject type and identifier
- request identifier
- IP address
- user agent
- before and after payloads
- structured metadata
- optional reason
- event timestamp

Sensitive fields are redacted recursively, including passwords, password confirmations, tokens, session identifiers, secrets, API keys, and remember tokens.

## Phase 1A Coverage

Audited foundation events include hospital profile changes, facility create/update/status/primary changes, department changes, staff invitation/update/suspension/reactivation, facility membership changes, role/permission assignment changes, settings changes, number-sequence changes, and number allocation.

Audit records are read-only through the administration interface. No normal administration workflow edits audit events.

