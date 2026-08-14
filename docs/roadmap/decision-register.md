# Decision Register

Date: 2026-08-14

| Decision | Recommended Default | Impact | Approval Needed | Healthcare Professional Input |
|---|---|---|---|---|
| Source control status | Approved for Phase 0: initialize local Git if no parent repository exists; no remote push | Essential for safe recovery and commercial development | Approved for Phase 0 | No |
| Frontend architecture | Approved for Phase 0 revision: Laravel modular monolith with Inertia.js and Vue 3 | Establishes SPA-style monolith without a separate API or client router | Approved for Phase 0 | No |
| Inertia/Vue adoption | Approved and implemented in Phase 0; do not add Vue Router, Pinia, or API auth unless later justified | Migrates Blade/Livewire/Volt UI to Vue while keeping Laravel routes/session auth | Approved for Phase 0 | No |
| Deployment model | Hybrid strategy, starting with isolated hospital deployments | Reduces tenant leakage risk in early releases | User | No |
| Tenancy in initial release | Design for hospital/facility scope, but do not implement shared SaaS tenancy yet | Keeps MVP practical while avoiding dead-end schema | User | No |
| User name schema | Approved for Phase 0: keep `firstname`/`lastname`, add computed display name | Fixes current auth/test/admin mismatch | Approved for Phase 0 | No |
| Role model | Approved for Phase 0: keep Spatie Permission and protect admin routes by role | Gives maintainable RBAC foundation | Approved for Phase 0 | No |
| `access_level` column | Phase 0: retain but avoid using as primary authorization source; document later deprecation | Avoids destructive schema change while roles remain source of authorization | Approved for Phase 0 | No |
| CMS retention | Approved for Phase 0: preserve source/tables/content but defer editing | Prevents broken CMS from blocking HMS work | Approved for Phase 0 | No |
| Public website scope | Approved for Phase 0: treat public pages as marketing/informational only | Avoids confusing UI placeholders with real modules | Approved for Phase 0 | No |
| Patient numbering | Hospital/facility-scoped configurable sequences | Affects imports, labels, receipts, forms | User | Hospital admin input |
| Patient duplicate detection | Start with configurable demographic/contact matching and manual review | Reduces duplicate records without unsafe auto-merge | User | Front desk/admin input |
| Clinical note amendment | Append-only amendment/correction workflow | Prevents silent clinical overwrite | User | Doctors/nurses |
| Encounter templates | Start generic, add specialty templates after pilot feedback | Speeds MVP | User | Doctors/nurses |
| Billing workflow | Support cash/deposit/payment-after-service basics first | Affects cashier operations and patient flow | User | Accountants/admin |
| Discount/refund workflow | Require approval and audit trail | Financial control | User | Accountants/admin |
| Lab result approval | Require result entry plus verification/approval statuses | Clinical safety | User | Laboratory scientists |
| Critical lab results | Require professional-defined critical flags and escalation | Patient safety | User | Laboratory scientists/doctors |
| Radiology attachments | Store reports/attachments behind authorized file access | PHI protection | User | Radiology staff |
| Pharmacy dispensing | Transactional dispensing with batch/expiry traceability | Stock and patient safety | User | Pharmacists |
| Drug interaction checking | Future integration only; do not implement unsupported homemade claims | Avoids unsafe clinical advice | User | Pharmacists/doctors |
| Inventory valuation | Choose FIFO/weighted average/manual reporting strategy later | Accounting impact | User | Accountants/storekeepers |
| Admissions MVP status | Defer until outpatient, billing, lab, pharmacy are stable | Reduces scope risk | User | Doctors/nurses/admin |
| Blood bank implementation | Defer; validate workflow with professionals before schema/code | Safety-critical chain of custody | User | Blood-bank professionals/doctors/nurses |
| Insurance/HMO | Defer until billing core is stable | Avoids premature claims complexity | User | Accountants/HMO officers |
| Notifications | Start with internal/log/email, add SMS after consent/template policy | Privacy and cost impact | User | Admin/legal/privacy input |
| File storage | Private disk with signed/temporary access | Protects documents/results | User | No |
| Audit logging package vs custom | Decide during Phase 1; custom domain audit may be clearer | Affects all modules | User | No |
| PHP environment | Phase 0 documented: enable `intl` in `C:\xampp\php\php.ini` | Required for Laravel tooling and production reliability | User/system admin | No |
| Test database | Phase 0 uses isolated SQLite in-memory fallback; dedicated MySQL test database remains preferred | Prevents tests touching development data | Approved for Phase 0 fallback | No |
| Nigeria privacy compliance | Treat as a legal/compliance workstream, not only technical | Commercial risk | User/legal counsel | Privacy/compliance officer |
| Backup strategy | Automated database/files backup with restore drills | Required before pilot | User/system admin | No |
