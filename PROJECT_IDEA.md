# V Traco Project Idea

## Project Title

V Traco - Employee Attendance, Project Allocation, Incentive, Reimbursement, and Payroll Management System

## Project Overview

V Traco is a web-based attendance and payroll management system designed for organizations that manage regular employees, contractual employees, external vendors, and project-based work. The system helps administrators assign employees to projects, manage attendance through manual punch and biometric imports, verify employee sessions, calculate salary, track incentives, handle reimbursements, and maintain payment records.

The project is built around a simple idea: attendance should be connected to the work an employee is actually assigned to. Instead of only marking a day as present or absent, V Traco allows attendance to be tied to assigned projects, session details, project dates, and incentives.

## Problem Statement

Many organizations manage employee attendance, project allocation, reimbursement, and payroll separately. This creates problems such as:

- Employees marking attendance for projects they are not assigned to.
- Admins manually verifying attendance without project context.
- Project-based incentives being difficult to track.
- Bulk biometric attendance imports overwriting manually verified attendance.
- Payroll calculations being disconnected from attendance status.
- Reimbursement and incentive payments being tracked outside the attendance system.

V Traco solves these problems by bringing these workflows into one system.

## Main Users

### Admin

The admin manages employees, projects, attendance, incentives, reimbursements, and payroll.

Admin responsibilities include:

- Add employees manually or through CSV import.
- Create and manage projects.
- Assign projects to employees with date ranges and incentives.
- Allocate shift timing and employee active date ranges.
- Import biometric attendance reports.
- Verify employee manual punch sessions.
- Update attendance status after verification.
- Review reimbursements.
- Manage salary, incentives, reimbursements, and payment history.

### Employee

Employees use the system to view attendance, submit manual punch details, and request reimbursements.

Employee responsibilities include:

- View monthly attendance calendar.
- Submit Manual Punch In for assigned project dates.
- Submit Manual Punch Out with session details.
- View assigned project-based manual punch options.
- Request reimbursements.
- View reimbursement status.

### External Vendor

External vendors can manage their own assigned employees after completing their profile.

Vendor responsibilities include:

- Add vendor employees.
- Manage vendor employee attendance.
- View employee logs.

### Contractual Employee

Contractual employees can use the employee workspace like regular employees but may be managed under a freelancer or contractual workflow.

## Core Modules

## 1. Authentication Module

The system supports login and registration flows for different user types:

- Admin
- Employee
- Contractual Employee
- External Vendor
- Super Admin

This module controls role-based access and redirects users to the correct workspace.

## 2. Employee Management Module

Admins can manage employees through:

- Manual employee creation.
- CSV employee import.
- Employee edit and delete.
- Password reset.
- Employee type selection.

Employee records include:

- Employee ID
- Name
- Email
- Phone number
- Shift
- Salary
- Employee type
- Assigned admin/vendor

## 3. Project Management Module

Admins can create projects with:

- Project name
- College name
- Location
- Total days
- Session type
- Active/inactive status

Projects can be assigned to employees. Assignment includes:

- Project date range
- Project incentive

Only active assigned projects are shown to employees for manual punch.

## 4. Time Allocation Module

Time Allocation is used for assigning:

- Shift timing
- Employee From date
- Employee To date

Manual Punch and Biometric Punch controls are intentionally removed from Time Allocation so that project and attendance rules stay cleaner.

## 5. Project Allocation Module

Project Allocation lets admins assign projects to selected employees.

For each selected project, admin can enter:

- From date
- To date
- Incentive amount

If multiple projects are assigned on the same date, the employee sees separate Manual Punch In and Manual Punch Out sections for each project.

## 6. Attendance Module

Attendance is shown in a monthly calendar.

Attendance statuses include:

- Present
- Absent
- Half Day
- Leave
- Week Off
- Pending

Manual punch attendance stays Pending until admin verification.

## 7. Manual Punch Module

Employees can submit manual punch only for assigned active project dates.

Manual Punch In includes:

- Punch photo upload
- Location coordinates
- Project ID
- Date

Manual Punch Out includes:

- Project
- College name
- Session name
- Full day or half day
- Session duration
- Location

After Manual Punch Out, attendance remains Pending. Admin must verify the session details and update attendance status.

## 8. Admin Attendance Verification

Admin reviews employee attendance and session details from the Employee Log calendar.

Admin can manually set attendance as:

- Present
- Absent
- Half Day
- Leave
- Week Off

Once admin updates the status, that status is preserved even after future bulk imports.

## 9. Bulk Attendance Import Module

Admins can upload biometric attendance files in formats such as:

- XLSX
- XLS
- CSV
- TXT

The importer reads employee attendance using employee code/name and attendance columns such as:

- Date
- INTime
- OUTTime
- Status
- Remark

Bulk import updates biometric punch times and shift timing. If admin has already verified a status manually, the import does not overwrite that admin decision.

## 10. Incentive Module

Admins can assign project incentives during Project Allocation.

The attendance summary can show assigned incentive totals for the selected month. Earned incentive is based on completed and verified project sessions.

Half-day project sessions count as half incentive.

## 11. Salary Calculation Module

Salary is calculated based on monthly salary and attendance.

Basic formula:

```text
working_days = total_days_in_month - leave_days - week_off_days

payable_days = present_days + (half_days * 0.5)

calculated_salary = monthly_salary * (payable_days / working_days)
```

Pending attendance does not count as paid attendance until admin verifies it.

## 12. Reimbursement Module

Employees can submit reimbursement requests for eligible dates.

Reimbursement request includes:

- Date
- Category
- Description
- Amount
- Attachment

Admins can review, approve, deny, partially pay, or mark reimbursements as paid.

## 13. Accounts and Payment Module

The Accounts module helps admins manage payments such as:

- Salary
- Incentive
- Reimbursement
- Other payments

It can show pending payment requests, payment history, and calculated amounts.

## Key Workflow

### Employee Attendance Workflow

1. Admin creates projects.
2. Admin assigns projects to employees with date range and incentive.
3. Employee opens attendance calendar.
4. Employee selects a date.
5. System shows manual punch sections only for active assigned projects on that date.
6. Employee submits Manual Punch In.
7. Employee submits Manual Punch Out with session details.
8. Attendance remains Pending.
9. Admin verifies session details.
10. Admin updates attendance status.
11. Salary and incentive calculations use verified attendance.

### Bulk Import Workflow

1. Admin uploads biometric attendance report.
2. System matches rows to employees.
3. System imports biometric in/out times and status.
4. If admin already manually verified attendance, the verified status is preserved.
5. Admin sees updated attendance in the same selected employee/month calendar.

## Suggested Future Improvements

- Add a dedicated admin approval queue for pending manual punch sessions.
- Add notification when employees submit manual punch.
- Add project-wise attendance reports.
- Add project-wise incentive reports.
- Add export options for payroll, incentives, and reimbursements.
- Add employee mobile-friendly punch interface.
- Add GPS validation for project location.
- Add photo preview and fraud detection checks.
- Add role-based permission customization.
- Add super admin analytics dashboard.

## Expected Benefits

- Better control over project-based attendance.
- Clear admin verification before salary impact.
- Reduced payroll mistakes.
- Clean separation between time allocation and project allocation.
- Better handling of biometric imports.
- Transparent incentive tracking.
- Easier reimbursement and payment management.

## Conclusion

V Traco is a practical attendance and payroll management platform for teams that work across multiple projects, vendors, and employee types. Its strength is connecting attendance with assigned projects and admin verification, making payroll and incentives more reliable.
