Lightweight PHP + MySQL backend deployment

Files added to this project root:
- api_config.php  -> DB connection settings (edit before upload)
- api_auth.php    -> Authentication endpoints (login, signup, logout, current, set)
- api_users.php   -> Users endpoints (list, create)
- api_workers.php -> Workers endpoints (list, create)
- migrate.sql  -> SQL to create users/workers tables and seed demo data
- portfolio.sql -> SQL to create editable portfolio tables

Requirements:
- PHP 7.4+ with PDO MySQL enabled
- MySQL 5.7+ or MariaDB 10.3+
- Apache hosting with PHP enabled

Deployment steps (typical cPanel / shared hosting):
1. Create a MySQL database and a dedicated database user in your hosting control panel, then grant that user access to the database.
2. Copy .env.example to .env on your local machine or hosting environment and replace the placeholder values with your database details. The project also supports environment variables named DASHBOARD_DB_HOST, DASHBOARD_DB_PORT, DASHBOARD_DB_NAME, DASHBOARD_DB_USER, and DASHBOARD_DB_PASS.
3. If your host does not allow .env files, edit api_config.php and set host, port, dbname, user, and pass to match your hosting account.
4. In phpMyAdmin, select the hosting database and import migrate.sql, then portfolio.sql. These scripts do not create or select a database because shared hosting commonly disallows those operations.
5. Upload this folder as `portfolio-admin` to your hosting public folder (for example, public_html). Keep index.html and the api_*.php files together.
6. Visit https://your-domain.example/portfolio-admin/api_ping.php to verify the database connection, then open https://your-domain.example/portfolio-admin/.
7. The included .htaccess blocks directory listing and direct access to configuration, SQL, and documentation files. The portfolio upload directory stores normalized WebP files and blocks script extensions.

Security notes / next steps (required before public use):
- The seed accounts are admin@example.com / admin123 and john@example.com / user123; change or delete them immediately.
- Use HTTPS on your site and a dedicated database user. Never use a hosting root database account.
- Back up the database before importing again. The user seed is idempotent; worker seeds are inserted only when the workers table is empty.
- Portfolio mutations require an admin session and CSRF token; use HTTPS and rotate the seeded credentials before production.

For local setup details, see README.md.
