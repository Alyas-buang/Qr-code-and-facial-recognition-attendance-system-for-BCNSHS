# Qr code and facial recognition system for BCNSHS

MEMBERS: <br>Mark ed Sedigo Kadusale <br>
         Venz horace D. Carabot<br>
         Sigasig Jera Salvania<br>
         Babor julie Ann Audeje<br>
         Dhale joshua G. Aliviado

## UI (DaisyUI via package.json)

1. Install Node.js (includes npm).
2. Run `npm install`.
3. Build CSS once: `npm run build`
4. During development: `npm run dev`

Source file: `src/assets/css/app.css`  
Generated file used by pages: `public/assets/css/app.css`

## Database Migrations

Run this SQL migration before using student status toggles and optimized duplicate-face checks:

`database/migrations/20260328_security_schema_updates.sql`

Run this SQL migration before using present, late, and absent attendance reporting:

`database/migrations/20260415_attendance_status_updates.sql`

After running the SQL migration, backfill descriptor norms for existing rows:

`C:\xampp\php\php.exe database\migrations\backfill_face_descriptor_norm.php`

## Security Configuration

Set these values in `.env`:

- `APP_SECRET` (required)
- `ADMIN_PASSWORD_HASH` (required, result of `password_hash`)
- `ADMIN_USERNAME` (recommended)
- `APP_TIMEZONE` (optional, defaults to `Asia/Manila`)
- `ATTENDANCE_LATE_TIME` (optional, defaults to `08:00:00`)

The app now fails closed when required secrets are missing.

## Automated Tests

Install dependencies and run tests:

1. `composer install`
2. `composer test`



