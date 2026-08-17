# QR Code Laboratory Attendance Management System

A complete, self-contained PHP + MySQL + JavaScript attendance system for
school computer laboratories, built to run directly on **XAMPP** with no
external frameworks (no Composer, no Laravel/CodeIgniter — plain PHP,
prepared-statement MySQL, and vanilla JS/AJAX).

---

## ✨ Features

**Login**
- Role selector (Admin / Teacher / Student tabs) — the selected role must match
  the account being signed into, so no one can log in on the wrong portal
- Password = the user's own ID number (Admin ID / Employee No. / Student No.)

**Administrator**
- Dashboard with live stats + Chart.js analytics (trend, status share, lab usage)
- Laboratory Status grid (green/red live indicator per lab, based on whether
  a QR session is currently running there)
- Student / Teacher / Subject / Laboratory CRUD (search, filter, pagination, modals)
- Class assignment management (teacher ⇄ subject ⇄ lab ⇄ schedule)
- QR session oversight (view all active sessions, force-stop any session)
- Attendance monitoring with multi-filter search
- Report builder with **PDF export** (print-to-PDF) and **Excel export** (.xls)
- Notification broadcast to teachers/students

**Teacher**
- Dashboard of assigned classes
- Student enrollment management per class
- **Activate / Deactivate** a live QR attendance session
- Auto-generated QR code (rotates per session, cryptographically signed)
- Live "who just scanned" panel (AJAX polling, updates every 4s)
- Attendance history + dedicated "Late Students" report
- Notifications

**Student**
- Dashboard with attendance summary
- **QR Code Scanner** (camera-based, via html5-qrcode)
- Attendance history with filters
- Editable profile (contact number, photo, password)
- Notifications

**Attendance Rules (enforced server-side)**
- A scan is only accepted if the session is currently **active**
- The student must already be **enrolled** in that class
- A student can only scan **once** per session (DB unique constraint + app check)
- Status = `Present` if scanned on time, `Late` if more than **15 minutes**
  after the class's scheduled start time (configurable in `includes/config.php`)
- The QR payload is HMAC-signed server-side (`qr/qr_helper.php`) so it can't be
  forged or replayed for a different session

---

## 🗂 Folder Structure

```
attendance-system/
├── admin/              Admin pages + matching ajax_*.php controllers
├── teacher/             Teacher pages + ajax controllers
├── student/              Student pages + ajax controllers (incl. ajax_scan.php)
├── assets/
│   ├── css/style.css     Full design system (cards, tables, modals, toasts...)
│   └── js/app.js         Toasts, modal helpers, AJAX helpers, sidebar toggle
├── includes/
│   ├── config.php        DB credentials + app constants (EDIT THIS FIRST)
│   ├── db.php             PDO connection
│   ├── auth.php            Login / RBAC / session handling
│   ├── functions.php        Helpers: sanitization, pagination, notifications...
│   ├── header.php / sidebar.php / footer.php   Shared layout
├── database/
│   ├── schema.sql          Full DB schema + sample data (import this!)
│   └── generate_password.php  One-time bcrypt hash checker/generator
├── qr/
│   └── qr_helper.php       Builds/validates signed QR payloads
├── reports/                 (reserved for any saved report exports)
├── uploads/photos/          Student/teacher profile photo uploads
├── index.php, login.php, logout.php
└── README.md                 You are here
```

---

## 🚀 Setup on XAMPP

1. **Copy the project folder** into your XAMPP `htdocs` directory, e.g.:
   `C:\xampp\htdocs\attendance-system\` (or `/Applications/XAMPP/htdocs/` on Mac).

2. **Start Apache and MySQL** from the XAMPP Control Panel.

3. **Import the database**:
   - Open `http://localhost/phpmyadmin`
   - Click **Import** → choose `database/schema.sql` → **Go**
   - This creates the `qr_attendance_system` database with sample data.

4. **Set every demo account's password to its own ID number** (the app logs
   people in with their ID number as the password — Admin ID / Employee No. /
   Student No.):
   - Visit `http://localhost/attendance-system/database/reset_passwords_to_id.php`
   - It hashes and saves the correct password for every seeded account and
     prints a table showing exactly what each one is (see below).

5. **Check `includes/config.php`** — the defaults (`root` / no password / `localhost`)
   match a stock XAMPP install. Adjust `BASE_URL` if you renamed the project folder.

6. **Open the app**: `http://localhost/attendance-system/`. On the login screen,
   pick your role (**Admin / Teacher / Student**) with the tabs at the top of
   the form, then sign in with your username and ID number.

### Demo accounts (password = ID number)
| Role    | Username   | Password (ID number) |
|---------|------------|-----------------------|
| Admin   | `admin`    | `ADM-0001`            |
| Teacher | `tcruz`    | `EMP-001`              |
| Teacher | `jsantos`  | `EMP-002`              |
| Student | `s2023001` | `2023-0001`            |
| Student | `s2023002` | `2023-0002`            |
| Student | `s2023003` | `2023-0003`            |

> 🔒 Delete or move `database/reset_passwords_to_id.php` and
> `database/generate_password.php` once your accounts are set up — they're
> setup utilities, not meant to stay publicly accessible.

**Keeping the convention going:** when the Admin adds a new Student or Teacher
through the Admin panel, whatever is typed into the "Password" field becomes
their login password — the Add Student/Add Teacher forms auto-fill it with
the student/employee number as it's typed, so new accounts follow the same
"password = ID number" pattern automatically. Editing an existing account
leaves the password unchanged unless a new one is entered.

---

## 📸 Using the QR Scanner

The QR scanner requires **camera access**, which most browsers only allow on:
- `http://localhost/...` (fine for the same computer), or
- **HTTPS** (needed if a student is scanning from their own phone over Wi-Fi).

If testing from a phone against a XAMPP server on your local network, you'll
need to either use a tool like `ngrok` to get an HTTPS URL, or accept the
browser's "insecure origin" camera warning in a testing/dev context.

---

## 🧩 How the QR Attendance Flow Works

1. Teacher opens **Attendance Session**, selects a class, clicks **Activate**.
   - A new row is inserted into `attendance_sessions` with a random 32-byte
     `qr_token` and `scheduled_start` = today's date + the class's official
     start time (from `teacher_subjects`).
2. The page renders a QR code (via `qrcode.min.js`) encoding a small signed
   JSON payload: `{ session_id, token, sig }` (see `qr/qr_helper.php`).
3. Student opens **QR Scanner**, camera reads the code, and the raw text is
   POSTed to `student/ajax_scan.php`.
4. The server:
   - Recomputes the HMAC signature and rejects mismatches
   - Confirms the session is still `is_active = 1`
   - Confirms the student is `enrolled` in that class
   - Confirms no existing `attendance_records` row for this student+session
   - Compares `NOW()` to `scheduled_start + late_threshold_minutes` to decide
     `Present` vs `Late`
   - Inserts the record and returns the result
5. Teacher's session page polls `ajax_session_status.php` every 4 seconds to
   show newly scanned students live.
6. Teacher clicks **Deactivate** (or admin force-stops it) to close the session.

---

## 🔐 Security Notes

- All queries use **PDO prepared statements** — no raw string concatenation of
  user input into SQL anywhere in the app.
- Passwords are hashed with **bcrypt** (`password_hash` / `password_verify`).
- Session ID is regenerated on login (`session_regenerate_id`) to mitigate
  session fixation.
- Role-based access control (`require_role()`) guards every protected page.
- QR payloads are HMAC-signed (`qr/qr_helper.php`) — change `QR_SECRET_KEY`
  in that file before any real deployment.
- File uploads (profile photos) are restricted to `jpg/jpeg/png/webp`.

---

## 🛠 Tech Stack

- **Backend**: PHP 8 (PDO, prepared statements, password_hash)
- **Database**: MySQL / MariaDB (InnoDB, foreign keys, indexes)
- **Frontend**: HTML5, CSS3 (custom design system, no Bootstrap dependency),
  vanilla JavaScript + `fetch`-based AJAX
- **Charts**: Chart.js (CDN)
- **QR generation**: qrcode.js (CDN)
- **QR scanning**: html5-qrcode (CDN)
- **PDF export**: browser print-to-PDF (no server library needed)
- **Excel export**: native `.xls` stream (Excel MIME type, no library needed)

No Composer, no Node build step — everything runs as-is once dropped into
`htdocs` and the database is imported.
