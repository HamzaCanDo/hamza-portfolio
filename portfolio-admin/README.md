# Dashboard

A lightweight PHP + MySQL dashboard with a single-page HTML frontend. The
frontend can run in local-storage demo mode or use the included PHP API and
MySQL database.

## Project files

- `index.html` - dashboard UI and frontend logic
- `api_auth.php` - login, signup, logout, current-user, and admin user switching
- `api_users.php` - user listing and creation endpoints
- `api_workers.php` - worker listing and creation endpoints
- `api_portfolio.php` - public manifest plus admin-only portfolio CRUD, image
  uploads/cropping, and atomic manifest publishing
- `api_ping.php` - database connectivity diagnostic endpoint
- `api_config.php` - local/hosting database connection settings
- `migrate.sql` - users/workers schema and demo seed data
- `portfolio.sql` - portfolio editor schema

## Local setup

1. Install PHP with PDO MySQL support and MySQL.
2. Create/import the database with `migrate.sql`, then import `portfolio.sql`.
3. Copy `.env.example` to `.env` and set your database values, or update the
   fallback values in `api_config.php` for your local database. Hosting
   credentials can also be supplied through `DASHBOARD_DB_*` environment
   variables.
4. Serve this directory through a PHP-enabled web server. Opening
   `index.html` directly from the filesystem will not enable the PHP API.

The production admin panel URL is `https://hamzaa.top/portfolio-admin/`.

## Portfolio API

Importing `migrate.sql` creates tables for site images, skills, projects,
project screenshots, and About timeline items. `api_portfolio.php` exposes
public `GET ?action=manifest` and admin-protected resource actions:
`site_images`, `skills`, `projects`, `timeline`, and `project_screenshots`.
Mutations use `POST`, require an authenticated admin session and the
`X-CSRF-Token` returned by `GET ?action=csrf`, and publish
`../portfolio-content.json` atomically. The published shape is
`{images:{hero,about},skills:[{name,img}],projects:[{name,desc,images,demoUrl}],about:[{title,text}]}`.
Use `POST ?action=upload` with a
multipart `image`, `kind`, and record `id` to normalize/crop an image into
`uploads/portfolio`.

The seeded demo accounts are:

- `admin@example.com` / `admin123`
- `john@example.com` / `user123`

Change or remove these accounts before deploying anywhere public.

## Security notes

Use HTTPS, replace demo passwords with secure accounts, keep database
credentials out of version control, and keep the upload directory protected.

For the complete shared-hosting deployment checklist, see
`README_DEPLOY.txt`.
