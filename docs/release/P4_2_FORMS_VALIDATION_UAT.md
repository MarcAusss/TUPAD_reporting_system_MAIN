# P4.2 — Forms & Validation UAT

Perform both a successful submission and at least one deliberate validation failure for every applicable form. Confirm validation errors are readable and safe prior input remains available where appropriate.

## Authentication and account security

- Login with valid credentials.
- Invalid password is rejected.
- Repeated invalid login attempts trigger throttling.
- Temporary-password account is forced to change password.
- Password change requires current password, confirmation, minimum strength, and a different new password.

## User Administration

### Administrator

- Create Administrator and Focal without province assignment.
- Create TC only with an active Region V province.
- Change role and confirm province assignment is cleared when the destination role is not TC.
- Confirm only Administrator, Focal, and TC can be assigned.
- Confirm `Retired Account` cannot be assigned through user administration.
- Reset another user's password and confirm one-time temporary password behavior.

### Focal

- Create/edit/reset/deactivate TC.
- Submit Administrator/Focal/retired role values manually and confirm privilege expansion is rejected.

## ADL and allocations

- Create/edit ADL with valid fiscal/fund values.
- Record a permitted fund realignment and confirm financial arithmetic.
- Create allocations and confirm grant/admin/total arithmetic.
- Attempt invalid/over-budget amounts and confirm rejection.

## Official project profiling

New official projects must begin in **Ongoing Profiling**. Verify required fields including allocation, date received, title/nature of work, sponsor/partner, project series, TEVS date, implementation mode, canonical locations, aggregate beneficiaries, work duration, wage rate, and insurance/PPE data when applicable.

Validation cases include female beneficiaries greater than total, mismatched geographic hierarchy, multi-location beneficiary totals that do not reconcile, and project cost exceeding the available allocation.

## TSSD / approval workflow

- Open a project in Ongoing Profiling and use the sticky **Next Workflow Action** control.
- Confirm the quick-action modal can submit Ongoing Profiling → TSSD Evaluation without scrolling to the bottom of the page.
- Record compliant evaluation to For Approval.
- Record deficiency to For Compliance.
- Resubmit compliance and continue to For Approval.
- Approve and confirm official project code/status transition.
- Confirm the detailed Workflow tab remains available as a fallback.

## Implementation

### Direct Administration

- Record required insurance/PPE/NTP/orientation/work-period details.
- Start implementation only when required data is complete.
- Move through Ongoing Implementation and Post-Documents using authorized actions.

### Through ACP

- Record ACP payment.
- Record check release and attachment where required.
- Record implementation.
- Record liquidation/partial liquidation and attachment where required.

## Post-documentary requirements and payment

- Submit valid post documents.
- Reject invalid/missing required document metadata.
- Record DA obligation/disbursement without exceeding allowed amounts.
- Confirm project reaches Completed only through the authoritative workflow.
