# P4.8 — Final System Functions & User Manual

## 1. System purpose

The TUPAD Reporting System supports DOLE Regional Office V workflows for fund allocation, official project profiling, evaluation/approval, implementation, payments, monitoring, reporting, geographic analysis, user administration, notifications, and audit review.

## 2. User roles

### Administrator
Regional system administration and full operational oversight. Can manage supported user roles, project operations, financial workflows, reports, notifications, and Audit Trail.

### Focal
Regional fund-monitoring and financial role. Can manage ADL/fund records, Direct Administration payment, ACP financial workflow, reports, and TC user accounts. Does not perform TC project evaluation/implementation mutations.

### TUPAD Coordinator (TC)
Province-scoped operational role. Creates official projects, handles evaluation/compliance/approval and implementation workflows within the assigned province, opens province-scoped reports, and manages their own password.

The former GIP account and project-draft workflow is retired. Historical former-GIP user rows may remain only as inactive `Retired Account` records to preserve audit/foreign-key history; they are not assignable roles and cannot use the system.

## 3. Main workflow

### Common start

1. ADL / Fund Allocation
2. **Ongoing Profiling** — initial status of every newly created official project
3. **TSSD Evaluation** — first progression after profiling
4. Optional For Compliance branch
5. For Approval
6. Approved

The project detail page includes a sticky **Next Workflow Action** area. For Ongoing Profiling, TSSD Evaluation, For Compliance, and For Approval, the next authorized action can be completed through a modal near the top of the workspace. The full Workflow tab remains available for detailed review.

### Direct Administration
Approved → preparation/implementation requirements → For Implementation → Ongoing Implementation → For Submission of Post-Docs → For Payment → Completed

### Through ACP
Approved → ACP Payment → For Release of Check to Proponent → implementation → For Liquidation / Partially Liquidated → Completed

## 4. Dashboard
Dashboard content is role-aware. Operational/financial users see current action queues and aging indicators. Aging thresholds prioritize work only; they do not automatically change status.

## 5. Project Registry and Workspace
Use Project Registry to search/filter official projects. Opening a project provides Overview, Beneficiaries, Workflow, Financial, and History tabs. Available actions depend on role, implementation mode, status, and province scope.

## 6. Notifications
Notifications are derived from current official workflow state. Resolving the underlying action removes the notification; notifications do not duplicate workflow status.

## 7. Audit Trail
Administrator only. Filters include module, action, actor, date, and search. Audit Trail is read-only. Sensitive password and remember-token values are excluded from audit detail.

## 8. User Accounts
Administrator can manage Administrator, Focal, and TC accounts. Focal can manage TC accounts only. TC requires an active Region V province. Retired historical accounts cannot be assigned through normal user administration.

## 9. Reports
Admin, Focal, and TC can access reports subject to role/province scope. Official outputs support browser print and relevant PDF/XLSX/CSV formats. Document control includes reference, version/revision, generated-by information, classification, and signatory blocks.

## 10. Geographic Mapping
Regional users can inspect Region V data. TC views remain assigned-province scoped. Map selections and supporting tables represent the same permitted cohort.

## 11. Password and account handling
- Never share passwords.
- Temporary passwords must be changed at first sign-in.
- TC uses My Account for password changes.
- Administrator/Focal reset passwords only for accounts they are authorized to manage.
- Deactivate accounts that should no longer sign in; preserve historical references.

## 12. Common troubleshooting

### 403 Forbidden
Usually indicates role, status, or TC province scope does not authorize the action. Verify assignment and workflow state instead of bypassing middleware.

### 404 on a project/province for TC
Province-scoped queries may intentionally fail closed outside the TC assignment.

### CSS/JS appears stale
Run `php artisan optimize:clear` and `npm run build`; restart `composer run dev` for local Vite development if needed.

### Production release rejected
Run `php artisan tupad:release-verify --production` and `php artisan tupad:phase4-audit --production`, then resolve the blocking message rather than using destructive reset commands.
