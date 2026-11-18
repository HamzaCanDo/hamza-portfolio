Student Database CRUD App (MySQL)

Setup for hosting
1) Create a MySQL database and user in your hosting control panel.
2) Import database.sql into that database (phpMyAdmin -> Import).
3) Open config.php and set your DB credentials, or set env vars:
   DB_HOST, DB_USER, DB_PASS, DB_NAME.
4) Upload this folder to your hosting (public_html or a subfolder).
5) Visit the site in your browser.

Requirements
- PHP 7.4+ with mysqli enabled
- MySQL 5.7+ or MariaDB 10.2+
