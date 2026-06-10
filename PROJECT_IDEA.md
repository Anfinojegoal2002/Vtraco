# V Traco Project Idea

## Project Title

V Traco - Attendance, Employee Log, Reimbursement, Accounts, and Payroll Management System

## Project Overview

V Traco is a web-based attendance and payroll management system for organizations that manage regular employees, contractual employees, external vendors, and project-based work. The website brings employee onboarding, attendance tracking, project assignment, reimbursement requests, accounts payment records, reports, and notifications into one role-based platform.

The core idea is simple: attendance should not be an isolated daily mark. It should connect to the employee, their assigned projects, manual or biometric punch records, admin verification, reimbursement claims, and payroll/accounting workflow.

## Problem Statement

Many organizations track employee attendance, project allocation, reimbursements, and payroll in separate files or tools. This causes issues such as:

- Employees being marked present without clear project or session context.
- Admins spending extra time checking manual punch details.
- Vendor and contractual employee records being mixed with regular employees.
- Biometric imports overwriting manually checked attendance decisions.
- Salary, incentives, and reimbursement payments being calculated outside the attendance system.
- Employees lacking visibility into attendance, reimbursement status, and payment updates.

V Traco solves this by combining attendance, employee logs, project allocation, reimbursement, accounts, reports, and notifications in one web application.

## Main Users

### Admin

The admin manages employees, projects, rules, attendance, reimbursements, accounts, reports, and vendor/contractual workflows.

Admin responsibilities include:

- Add employees manually or through file import.
- View employee lists with employee role, type, salary, shift, and rules.
- Create and manage projects.
- Assign employees to active projects.
- Configure attendance rules and shift timing.
- Import biometric attendance reports.
- Review employee attendance calendars and session details.
- Override daily attendance status when needed.
- Review, approve, deny, partially pay, or pay reimbursement requests.
- Process salary, incentive, reimbursement, and other account payments.
- View reports and export attendance data.

### Employee

Employees use the system to view their attendance calendar, submit attendance details, and manage reimbursements.

Employee responsibilities include:

- View monthly employee log calendar.
- Submit manual punch-in and manual punch-out when rules allow it.
- Submit biometric attendance when enabled.
- Request leave.
- Submit reimbursement claims for eligible dates.
- View reimbursement and payment notifications.
- Change password and access profile settings.

### External Vendor

External vendors can access their own vendor workspace after profile completion.

Vendor responsibilities include:

- Add and manage vendor employees.
- View vendor employee logs.
- Track employee attendance and notifications within the vendor scope.

### Contractual / Corporate Employee

Contractual employees use the employee workspace but are handled through the corporate/contractual flow. Their salary can be calculated from completed sessions instead of standard monthly attendance.

### Super Admin

The super admin manages higher-level administrative access and approval of admin accounts.

## Core Modules

## 1. Authentication and Role-Based Access

The website supports login and access flows for:

- Admin
- Employee
- Corporate Employee
- External Vendor
- Freelancer / Corporate manager
- Super Admin

Each role is routed to the correct dashboard and sidebar. Admin, vendor, employee, and corporate users see different workspaces based on their responsibilities.

## 2. Employee Management

Admins and eligible managers can manage employees through:

- Manual employee creation.
- Bulk employee import.
- Employee edit and delete.
- Password reset.
- Employee role display.
- Employee type selection: regular, vendor, or corporate.
- Rules and project assignment.

Employee records include:

- Employee ID
- Name
- Email
- Phone number
- Role
- Shift
- Salary or session rate
- Employee type
- Assigned admin/vendor

## 3. Project Management

Admins can create and maintain project records with:

- Project name
- College name
- Location
- Project dates
- Session details
- Active/inactive status

Projects can be assigned to employees. Assigned projects determine which manual punch options are available to employees on specific dates.

## 4. Rules and Shift Management

The Rules section controls how employees can record attendance.

Rules can include:

- Manual punch-in permission.
- Manual punch-out permission.
- Number of manual punch slots.
- Shift selection.
- Employee active date range.
- Biometric attendance permissions.

Admins can apply rules to selected employees and notify employees when rules are updated.

## 5. Attendance and Employee Log Calendar

Attendance is displayed in a monthly calendar for both admins and employees.

Attendance statuses include:

- Present
- Absent
- Half Day
- Leave
- Week Off
- Pending

The Employee Log calendar lets admins inspect each date, view manual session details, review biometric punch data, and update attendance status.

## 6. Manual Punch and Biometric Attendance

Employees can submit attendance based on the rules assigned by their admin.

Manual Punch In can include:

- Punch photo upload
- Location coordinates
- Project/session context
- Punch time

Manual Punch Out can include:

- College name
- Session name
- Full day or half day selection
- Session duration
- Total students
- Present students
- Topics handled
- Location

Biometric imports can load in/out times and attendance status from uploaded files.

## 7. Bulk Attendance Import

Admins can upload biometric attendance reports in formats such as:

- XLSX
- XLS
- CSV
- TXT

The importer reads employee and attendance columns such as:

- Employee code / Emp ID
- Date
- INTime
- OUTTime
- Status
- Remark

If an admin has already manually changed a status, the import preserves that admin override instead of replacing it.

## 8. Salary Calculation

Salary calculation is connected to the attendance calendar.

For regular employees:

```text
working_days = total_days_in_month - leave_days - week_off_days

payable_days = present_days + half_days

calculated_salary = monthly_salary * (payable_days / working_days)
```

In the current website rule, Half Day is counted as a full payable day for salary.

Absent, Pending, and unmarked days do not add payable salary. Leave and Week Off are removed from the working-day denominator.

For vendor or corporate/contractual employees, salary can work as a session rate:

```text
calculated_salary = full_sessions * session_rate + half_sessions * (session_rate / 2)
```

Only completed manual sessions count toward session-based salary.

## 9. Incentive Management

Project/session activity can generate incentives based on completed sessions and project allocation rules.

The calendar summary can show:

- Total present days
- Half days
- Incentive amount
- Calculated salary

## 10. Reimbursement Management

Employees can submit reimbursement requests from the monthly reimbursement calendar.

Each reimbursement can include:

- Expense date
- Category
- Description
- Requested amount
- Attachment/proof

Admins can:

- View reimbursement requests.
- Preview uploaded proof.
- Approve or deny requests.
- Mark partial payment.
- Mark full payment.
- Record reimbursement payment through Accounts.
- Export recent reimbursements.

## 11. Accounts and Payments

The Accounts module manages financial processing for:

- Salary
- Incentive
- Reimbursement
- Other payments

It supports:

- Approval queues.
- Payable amount review.
- Employee/vendor payment grouping.
- Payment method selection.
- Transaction ID tracking.
- Proof of payment upload.
- Payment history and reports.
- Payslip/payment email notifications.

## 12. Reports

The Reports section helps admins review attendance and session history.

Reports can include:

- Date
- Employee name
- Attendance source
- Project name
- Slot/session type
- Attendance status
- Manual punch-in and punch-out details
- Biometric punch-in and punch-out details
- Student/session data
- Topics handled

Reports can be filtered and exported.

## 13. Notifications

The notification system keeps users informed about:

- Payment updates
- Reimbursement updates
- Account activity
- Important workflow changes

Unread notifications are shown in the sidebar.

## Key Workflows

### Employee Attendance Workflow

1. Admin creates employees and assigns rules.
2. Admin creates and assigns projects when needed.
3. Employee opens the employee log calendar.
4. Employee selects a date.
5. System shows available manual or biometric attendance options.
6. Employee submits punch-in and punch-out details.
7. Admin reviews the date in Employee Log.
8. Admin updates or confirms attendance status.
9. Salary, incentive, and reports use the updated attendance data.

### Salary Workflow

1. Employee attendance is resolved for the selected month.
2. System counts Present and Half Day as payable days for regular salary.
3. System removes Leave and Week Off from working days.
4. System calculates monthly payable salary.
5. Accounts subtracts salary already paid for that month.
6. Remaining salary can be processed as a payment.

### Reimbursement Workflow

1. Employee opens the reimbursement calendar.
2. Employee submits a reimbursement request with amount, category, description, and proof.
3. Admin reviews the request.
4. Admin approves, denies, partially pays, or pays the request.
5. Payment details are recorded in Accounts.
6. Employee receives notification/email update.

### Bulk Attendance Import Workflow

1. Admin uploads attendance file.
2. System maps report rows to employees.
3. System imports biometric times and status.
4. Existing admin overrides are preserved.
5. Admin reviews the updated calendar and reports.

## Expected Benefits

- One place for employee attendance, project logs, reimbursements, accounts, and reports.
- Better visibility for admins, employees, vendors, and corporate users.
- Clear attendance-driven salary calculation.
- Reduced payroll and reimbursement tracking mistakes.
- Stronger handling of manual punch and biometric import together.
- Separate workflows for regular, vendor, and contractual employees.
- Payment history and notification trail for finance-related actions.

## Suggested Future Improvements

- Add a dedicated approval queue for pending manual punch sessions.
- Add stronger payroll finalization and monthly lock.
- Add allowance and deduction support.
- Add payroll export and payslip generation for salary.
- Add GPS validation for project locations.
- Add project-wise attendance and incentive reports.
- Add richer dashboard analytics.
- Add more granular role permissions.
- Add automated test coverage for critical flows.
- Add production deployment documentation and backup process.

## Conclusion

V Traco is a practical attendance and payroll web platform for organizations that manage employees, vendors, contractual workers, projects, reimbursements, and payments. Its strength is connecting daily attendance with project/session records, admin verification, salary calculation, reimbursement approval, and accounts payment tracking.
