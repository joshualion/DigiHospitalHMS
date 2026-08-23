# Laboratory Operations Guide

Status: Phase 4A.

## Catalogue Setup

Use **Admin > Laboratory > Catalogue** to configure specimen types, units, tests, components, reference ranges and panels.

Reference ranges and critical thresholds must be validated by laboratory professionals before clinical use. Seeded examples are structural placeholders only.

## Ordering

Authorized clinicians or staff can order requests from **Admin > Laboratory**. A request can reference a patient, visit and encounter. Selecting a test panel expands the configured tests into the request.

If a selected test is mapped to a billable service, the system creates draft invoice lines through the billing backend.

## Specimen Workflow

Laboratory staff can:

- Collect specimens and generate labels.
- Receive specimens in the lab.
- Reject specimens with a mandatory reason.
- Collect replacement specimens while preserving the rejected-specimen history.

## Result Workflow

Laboratory staff enter draft results by component. Authorized staff verify draft results. Authorized approvers approve verified results and may release the report.

Critical results can be acknowledged with escalation notes. The system records the actor and timestamp.

## Reports And Corrections

Only approved or released reports are printable. Corrections do not overwrite approved results; use report amendments with a reason and correction note.
