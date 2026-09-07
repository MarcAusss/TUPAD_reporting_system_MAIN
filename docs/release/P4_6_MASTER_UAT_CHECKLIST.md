# P4.6 — Master User Acceptance Test Checklist

Record Pass/Fail and remarks for every applicable item.

## Administrator UAT

| Test | Expected result | Result | Remarks |
|---|---|---|---|
| Login / dashboard | Regional dashboard opens |  |  |
| User Accounts | Administrator/Focal/TC visible and manageable |  |  |
| Retired account role | Not assignable or shown as a live role |  |  |
| Audit Trail | Accessible and filterable |  |  |
| Project Registry | Accessible |  |  |
| Create project | Starts in Ongoing Profiling |  |  |
| Quick Next Action | Sticky action opens progression modal |  |  |
| Ongoing Profiling → TSSD | Can progress without long page scroll |  |  |
| Workflow queues | Correct authorized queues available |  |  |
| Payments / ACP finance | Available |  |  |
| Reports / exports | Available |  |  |

## Focal UAT

| Test | Expected result | Result | Remarks |
|---|---|---|---|
| Dashboard | Financial queues visible |  |  |
| User Accounts | TC accounts only |  |  |
| ADL / funds | Authorized functions available |  |  |
| DA payment | Available |  |  |
| ACP financial workflow | Available |  |  |
| Project mutation | Unauthorized where TC operational action is required |  |  |
| Reports | Available |  |  |

## TUPAD Coordinator UAT

| Test | Expected result | Result | Remarks |
|---|---|---|---|
| Dashboard | Province-scoped operational queues visible |  |  |
| Project creation | Allowed within assigned province |  |  |
| Initial status | Ongoing Profiling |  |  |
| Quick Next Action | Sticky action remains accessible near page top |  |  |
| Submit to TSSD | Modal/quick action moves to TSSD Evaluation |  |  |
| Evaluation/compliance/approval | Authorized progression works |  |  |
| Other province project | Access denied/fails closed |  |  |
| Implementation workflow | Province-scoped actions available |  |  |
| Reports | Province-scoped |  |  |
| My Account | Password self-service available |  |  |

## Retired GIP feature verification

| Test | Expected result | Result | Remarks |
|---|---|---|---|
| GIP role assignment | Not available |  |  |
| GIP sidebar/dashboard | Absent |  |  |
| Project draft routes | Absent |  |  |
| Project draft tables | Absent after migration |  |  |
| Historical former GIP user | Inactive retired account only, if present |  |  |

## Cross-system QA

| Test | Expected result | Result | Remarks |
|---|---|---|---|
| Mobile sidebar | Opens/closes without trapping content |  |  |
| Keyboard focus | Visible on interactive controls |  |  |
| Empty states | Clear and non-technical |  |  |
| Global search | Relevant permitted official records only |  |  |
| Audit logging | Key changes recorded with actor/time |  |  |
| Document metadata | Version/reference/generated-by shown |  |  |
| Signatories | Authorized officials or blank signature lines |  |  |
| Full PHPUnit suite | Zero failures |  |  |
| Production audit | Zero blocking failures |  |  |

## UAT approval

- Business/Program representative: __________________ Date: __________
- System owner/Administrator: _______________________ Date: __________
- Technical handover representative: _______________ Date: __________
