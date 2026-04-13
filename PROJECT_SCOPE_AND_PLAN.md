# V Traco Project Scope and Plan

Updated: 2026-04-12

## 1. Project Summary

V Traco is a PHP attendance and payroll management web app built to run inside XAMPP. It currently supports two roles:

- Admin: creates and manages employees, assigns attendance rules, posts shift timings, imports attendance, reviews calendars, and updates statuses.
- Employee: signs in, views a monthly attendance calendar, submits manual punch-in and punch-out data, uses biometric stamps when enabled, requests leave, and manages password/profile basics.

The application is already beyond MVP stage. Core attendance and onboarding flows are implemented, but the project still needs documentation cleanup, security hardening, payroll expansion, and production-readiness work.

## 2. Current Product Scope

### 2.1 Public and Authentication Scope

- Landing page with product marketing and login entry points.
- Admin self-registration.
- Admin login.
- Employee login.
- Employee forgot-password flow that generates a new password and sends/logs it.
- Session-based authentication.
- Role-based routing between admin and employee workspaces.

### 2.2 Admin Scope

- Dashboard with employee totals and daily attendance summary.
- Employee management:
  - Add employee manually.
  - Bulk import employees by CSV.
  - Edit employee details.
  - Delete employee.
  - Reset employee password.
- Rules management:
  - Enable manual punch.
  - Enable biometric punch.
  - Configure manual punch slot count.
  - Apply rules to selected employees.
- Shift management:
  - Add shift timings.
  - Delete shift timings.
- Attendance management:
  - View employee month calendar.
  - Inspect day/session details in modal.
  - Override daily status.
  - Import attendance from `.xlsx`, `.csv`, `.txt`, and HTML-style legacy `.xls`.
  - Auto-create employee shells from attendance import when needed.
- Admin profile and password update.

### 2.3 Employee Scope

- View monthly calendar with status colors and salary preview.
- Manual punch-in with image upload and geo fields.
- Manual punch-out with college name, session name, day portion, duration, and location.
- Multiple manual punch slots per day.
- Biometric in/out when enabled by admin.
- Leave request per date.
- Password change.
- Client-side profile photo preview/storage in browser.

### 2.4 Attendance and Payroll Scope

- Sunday auto-marked as `Week Off`.
- Past dates without valid completion resolve to `Absent`.
- Manual punch sessions can resolve into `Pending`, `Present`, or `Half Day`.
- Leave and week-off statuses are preserved.
- Monthly salary is prorated from attendance days shown in the calendar.
- Daily attendance snapshot powers admin dashboard metrics.

### 2.5 Communication and Storage Scope

- PHPMailer integration for employee credentials and rule-update mails.
- SMTP support through config/env values.
- Mail HTML copies saved to `storage/emails/`.
- Punch images saved to `storage/uploads/punches/`.
- Sessions stored on disk under `storage/sessions/`.

## 3. Technical Scope

### 3.1 Stack

- Backend: plain PHP
- Database: MySQL via PDO
- Frontend: server-rendered PHP views, custom CSS, vanilla JS
- Dependency manager: Composer
- Mail: PHPMailer
- Hosting target: XAMPP / Apache

### 3.2 App Structure

- Entry point: `index.php`
- Boot/config: `src/bootstrap.php`, `config/*.php`
- Routing shell/layout: `src/layout.php`
- Business modules:
  - `src/core/database.php`
  - `src/core/employees.php`
  - `src/core/employee_management.php`
  - `src/core/attendance.php`
  - `src/core/actions.php`
  - `src/core/mail.php`
  - `src/core/support.php`
- Views:
  - `src/views/landing.php`
  - `src/views/auth.php`
  - `src/views/admin.php`
  - `src/views/employee.php`
- Assets:
  - `assets/css/app.css`
  - `assets/js/app.js`

### 3.3 Data Model in Scope

Current database tables indicate these domain objects:

- `users`
- `employee_rules`
- `shift_timings`
- `attendance_records`
- `attendance_sessions`

This means the app already supports multi-admin ownership of employees, rule-driven attendance behavior, per-day records, and multi-session manual attendance.

## 4. Core User Flows

### 4.1 Admin Flow

1. Register admin account.
2. Log in.
3. Add employees manually or import CSV.
4. Assign attendance rules.
5. Post shift timings.
6. Track attendance from dashboard and calendars.
7. Import attendance files for biometric-based updates.
8. Adjust status if needed.

### 4.2 Employee Flow

1. Receive credentials by email.
2. Log in.
3. Open date from attendance calendar.
4. Submit manual punch-in or biometric in.
5. Submit manual punch-out details if manual mode is enabled.
6. Request leave when needed.
7. Review monthly attendance and salary estimate.

## 5. Current Gaps, Risks, and Cleanup Items

These are the main issues visible from the current codebase and should be treated as part of the project scope:

- Documentation mismatch:
  - `README.md` still mentions SQLite storage, but runtime code is now MySQL-based.
- Security hardening needed:
  - Forms do not currently show CSRF protection.
  - Employee password reset by email is very lightweight and should be tightened.
  - SMTP secrets are stored in `config/mail.php`; they should be moved fully to environment variables and rotated if real.
- Payroll is basic:
  - Monthly salary is calculated from attendance presence only.
  - No payroll lock, payslip, deduction, allowance, or export flow exists yet.
- Approval workflow is limited:
  - Leave and manual attendance do not have a formal review/approval queue.
- No automated test suite is present.
- No audit trail exists for admin overrides, password resets, imports, or deletions.
- Profile photos are browser-local only, not server-managed.
- Single-file request routing is still simple and workable, but long-term maintainability will improve with clearer controller/service boundaries.

## 6. Full Project Scope Going Forward

The practical full scope for V Traco should be:

### 6.1 Product Scope

- Attendance management for admin and employee roles.
- Rule-based attendance capture using manual and biometric modes.
- Employee onboarding and lifecycle management.
- Shift definition and assignment.
- Leave and half-day handling.
- Payroll visibility and monthly salary calculation.
- Import/export support for attendance and employee data.
- Communication layer for onboarding and status notifications.

### 6.2 Operational Scope

- Configurable deployment for local, staging, and production.
- Database backups and recovery process.
- Mail configuration through environment variables only.
- Error logging and admin-visible diagnostics.
- Documentation for setup, import formats, and admin usage.

### 6.3 Security Scope

- CSRF protection.
- Safer password reset flow.
- Input validation hardening.
- Upload validation for punch photos and imported files.
- Secret management and environment separation.
- Action logging for sensitive admin operations.

### 6.4 Reporting and Payroll Scope

- Payroll-ready monthly summaries.
- Exportable reports for attendance and salary.
- Employee-wise attendance history.
- Status filters and summaries by day, month, and employee.

## 7. Recommended Delivery Plan

### Phase 1: Stabilize the Foundation

Goal: make the current app consistent, documented, and safe to continue building on.

Deliverables:

- Align README and setup docs with MySQL-based runtime.
- Move all sensitive config to env-based loading.
- Add validation review for onboarding, attendance import, and uploads.
- Add base error logging and admin-friendly failure messages.
- Clean obvious code/documentation drift.

Definition of done:

- Fresh setup works from docs.
- No production secrets are stored in repo config.
- Core flows still work after config cleanup.

### Phase 2: Security and Account Hardening

Goal: reduce risk in auth and admin operations.

Deliverables:

- CSRF protection on forms.
- Stronger employee reset flow.
- Password policy review.
- Action logging for login, reset, import, and delete actions.
- File upload restrictions and MIME validation.

Definition of done:

- Sensitive actions are protected and logged.
- Upload and account flows are meaningfully safer.

### Phase 3: Attendance Workflow Completion

Goal: make attendance operations easier to manage at scale.

Deliverables:

- Admin review queue for pending manual punches and leave.
- Better attendance detail views.
- Bulk attendance corrections.
- Improved shift assignment flow.
- Better import feedback for unmatched or auto-created employees.

Definition of done:

- Admin can review, approve, and correct attendance with less manual friction.

### Phase 4: Payroll Expansion

Goal: move from salary preview to usable payroll workflow.

Deliverables:

- Monthly payroll summary page.
- Deductions and allowances model.
- Payslip generation/export.
- Payroll lock/finalize step.
- CSV export for finance/admin use.

Definition of done:

- Payroll output can be reviewed, exported, and finalized per month.

### Phase 5: Reporting and Administration

Goal: improve visibility and operations.

Deliverables:

- Searchable attendance reports.
- Filters by employee, date range, status, and shift.
- Admin activity logs.
- Dashboard trend cards.
- Backup/import/export utilities.

Definition of done:

- Admin can answer common operational questions without direct database access.

### Phase 6: Production Readiness

Goal: make the system maintainable and deployable beyond a local XAMPP setup.

Deliverables:

- Environment-specific config handling.
- Deployment checklist.
- Backup/restore procedure.
- Basic test coverage for critical flows.
- Refactor of large modules where needed.

Definition of done:

- Project can be deployed, operated, and maintained with lower risk.

## 8. Suggested Priority Order

### P0

- Fix documentation drift.
- Remove secrets from repo config.
- Add CSRF protection.
- Tighten reset flow.
- Validate uploads and imports better.

### P1

- Add admin approval workflow for leave/manual attendance.
- Expand payroll into reports and exports.
- Add audit logging.

### P2

- Refactor large files.
- Add deeper analytics/dashboard trends.
- Add server-side profile/media management.

## 9. Suggested Milestone View

If we want a practical build sequence, this is the cleanest order:

1. Foundation and security
2. Attendance workflow completion
3. Payroll and reporting
4. Production readiness and refactor

## 10. Immediate Next Actions

The next concrete moves I'd recommend are:

1. Update setup/docs to reflect MySQL and current module structure.
2. Remove mail credentials from tracked config and switch to env-only secrets.
3. Add CSRF protection and review all form actions.
4. Introduce an admin approval state for manual attendance and leave.
5. Define the payroll outputs you want: summary only, CSV, or payslip.
6. Add a small smoke-test checklist for login, employee create, attendance submit, and import.

## 11. One-Line Project Positioning

V Traco is currently a working attendance management system with early payroll support; the right next plan is to harden security and operations first, then complete approval, payroll, reporting, and deployment readiness.
