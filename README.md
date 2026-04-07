# V Traco

V Traco is a PHP + SQLite employee attendance and payroll management system designed to run directly inside XAMPP.

## Run

1. Place the project inside `C:\xampp\htdocs\vtraco`.
2. Start Apache from XAMPP.
3. Open `http://localhost/vtraco/`.
4. Register the first admin account from the landing page.

## Notes

- Data is stored in `storage/data/app.sqlite`.
- Generated email messages are logged to `storage/emails/`.
- Punch-in images are stored in `storage/uploads/punches/`.
- CSV import expects these headers exactly:
  `Emp ID, Name, Email, Phone Number, Salary`

## PHPMailer Setup

- PHPMailer is installed through Composer in `vendor/`.
- Configure SMTP in `src/bootstrap.php` using `MAIL_SMTP_HOST`, `MAIL_SMTP_PORT`, `MAIL_SMTP_USERNAME`, `MAIL_SMTP_PASSWORD`, and `MAIL_SMTP_ENCRYPTION`.
- You can also override those values with environment variables named `VTRACO_MAIL_HOST`, `VTRACO_MAIL_PORT`, `VTRACO_MAIL_USERNAME`, `VTRACO_MAIL_PASSWORD`, and `VTRACO_MAIL_ENCRYPTION`.
- Employee credential emails use the logged-in admin email as the visible sender and reply-to address.
- If SMTP is not configured or delivery fails, the app still saves each email in `storage/emails/`.
