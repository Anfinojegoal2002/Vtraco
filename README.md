# V Traco

V Traco is a PHP + MySQL attendance and payroll management system designed to run inside XAMPP.

## Run

1. Place the project in `C:\xampp\htdocs\vtraco`.
2. Create a MySQL database user that can create databases, or use the default local XAMPP MySQL root account.
3. Review `config/database.php` and set the MySQL host, port, database name, username, and password.
4. Start Apache and MySQL from XAMPP.
5. Open `http://localhost/vtraco/`.
6. Register the first admin account from the landing page.

The app auto-creates the configured database schema on first load.

## Storage

- Email HTML copies are saved to `storage/emails/`.
- Punch-in images are saved to `storage/uploads/punches/`.
- Application errors are logged to `storage/logs/app.log`.
- PHP sessions are stored on disk under `storage/sessions/` when your PHP setup is configured that way.

## Mail Setup

Tracked config files no longer store SMTP secrets. Set these environment variables before starting Apache if you want email delivery:

- `VTRACO_MAIL_HOST`
- `VTRACO_MAIL_PORT`
- `VTRACO_MAIL_USERNAME`
- `VTRACO_MAIL_PASSWORD`
- `VTRACO_MAIL_ENCRYPTION`
- `VTRACO_MAIL_FROM_FALLBACK`

If SMTP is not configured or delivery fails, V Traco still saves each generated email to `storage/emails/`.

## Security Notes

- POST forms now require CSRF tokens.
- Employee password resets issue temporary passwords and force the employee to change them after sign-in.
- Employee self-service reset requests are rate-limited.
- Punch photo uploads accept only JPG, PNG, and WebP files up to 4 MB.
- Attendance imports accept `.xlsx`, `.csv`, `.txt`, and HTML-style legacy `.xls` files up to 8 MB.
- Employee CSV imports accept `.csv` files up to 2 MB and validate email, phone, and salary values.

## Employee CSV Format

Required columns:

- `Email`
- `Phone Number`
- `Salary`

Supported optional columns include:

- `Emp ID`
- `Name`
- `Shift`

Missing employee IDs can be generated automatically during CSV import.
