# CodeVault

Future-Ready Coding Solutions — a small PHP web app for sharing code, accounts, and simple collaboration.

This repository contains a PHP web frontend and a simple authentication backend. It is intended to run on a local XAMPP (Apache + MySQL + PHP) stack for development and evaluation.

## Contents

- `index.php` — main public landing page and client UI.
- `auth/` — authentication and account-related scripts (login, register, OAuth helpers, email helper).
- `send-mail.php` — example mail-send script.
- `sendmail.sql` — database import SQL (tables / sample data used by mail features).
- `PHPMailer/` — bundled PHPMailer library.
- `composer.json` — Composer dependencies (OAuth2 Google client).

## Requirements

- Windows (development instructions assume XAMPP on Windows)
- XAMPP (Apache, MySQL)
- PHP 7.4+ (or newer) with PDO and pdo_mysql extension enabled
- Composer (for dependency management if you want to re-install vendor files)

Optional (for OAuth / mailing):
- Google OAuth credentials (if you want to enable Google sign-in via `auth/oauth_google.php`)
- SMTP account details for sending email (used by `send-mail.php` and `auth/email_helper.php`)

## Quick setup (Windows / XAMPP)

1. Install XAMPP and start Apache & MySQL services.

2. Place the project folder under your web root (already present if using this repo):

   - Example webroot path: `C:\xampp\htdocs\CodeVault` (this repo appears at `c:\xampp\htdocs\WEBPAGES\CodeVault`).

3. Create the database and import schema/data.

   - Create the database named `codevault` (the default used by `auth/config.php`):

     ```powershell
     & 'C:\xampp\mysql\bin\mysql.exe' -u root -e "CREATE DATABASE IF NOT EXISTS codevault;"
     ```

   - Import the provided SQL file (run from the project root):

     ```powershell
     & 'C:\xampp\mysql\bin\mysql.exe' -u root codevault < .\sendmail.sql
     ```

   - Alternatively, open phpMyAdmin (http://localhost/phpmyadmin) and import `sendmail.sql` into the `codevault` database.

4. Verify database credentials in `auth/config.php`.

   - Defaults in this repo:

     - host: `localhost`
     - dbname: `codevault`
     - username: `root`
     - password: `` (empty)

   - For production or shared environments, change these values and keep credentials secret.

5. Install PHP dependencies (optional — vendor is already included in this workspace).

   - If you need to regenerate or install Composer packages:

     ```powershell
     cd C:\xampp\htdocs\WEBPAGES\CodeVault
     composer install
     ```

6. Configure mail settings

   - The project includes `PHPMailer/` and `send-mail.php` as an example. Look for SMTP configuration variables in `send-mail.php` or `auth/email_helper.php` and update them with your SMTP host, username, password, port, and encryption (tls/ssl).

   - Example (do not commit real credentials):

     - SMTP host: `smtp.example.com`
     - SMTP user: `user@example.com`
     - SMTP pass: `yourpassword`
     - SMTP port: `587` (TLS) or `465` (SSL)

7. (Optional) Google OAuth

   - The project lists `league/oauth2-google` in `composer.json`. If you want Google sign-in to work, create OAuth credentials in Google Cloud Console and configure the client ID/secret in `auth/oauth_google.php` or in your environment variables as the file expects.

## Run / Access

- Open your browser and go to: http://localhost/WEBPAGES/CodeVault/ (adjust path to where the repo lives in your XAMPP htdocs)

- Common pages:
  - Landing page: `index.php`
  - Register / Login: `auth/register.php`, `auth/login.php`
  - Dashboard and repo pages under `auth/`

## Security notes

- This project appears intended for local/dev use. Before deploying to production:
  - Do not leave default DB credentials in code. Use environment variables or a secure config mechanism.
  - Remove bundled vendor libraries you don't need or ensure they are up-to-date.
  - Ensure proper input validation and prepared statements (PDO is used in `auth/config.php` for connection). Review authentication and file access code (`auth/*`) for hardening.

## Troubleshooting

- If pages show errors, enable PHP error logging in `php.ini` or check Apache/PHP error logs in XAMPP.
- If database connection fails, confirm MySQL is running and credentials in `auth/config.php` match your MySQL setup.
- If mail fails, double-check SMTP host/port/credentials and allow less-secure-app access or use an app password if required by your provider.

## Useful files

- `composer.json` — composer dependencies
- `sendmail.sql` — SQL to import tables/sample data
- `auth/config.php` — database configuration and example table creation
- `PHPMailer/` — included mail library

## Next steps / optional improvements

- Move configuration out of versioned files into environment variables.
- Add a dedicated `.env` loader (vlucas/phpdotenv) and document usage.
- Add unit or integration tests for critical auth and email flows.

---

If you want, I can also:

- Add a `.env.example` and small setup script to make configuration easier.
- Scan `auth/` and `send-mail.php` and extract exact SMTP config locations and OAuth keys for the README.

If you'd like any of those, tell me which and I'll implement them next.
