PHP OOP Unique ID Generator
===========================

Requirements
------------
- PHP 7.4 or newer with PDO and pdo_mysql enabled
- MySQL 5.7+ or MariaDB
- Apache or another PHP-compatible web server

Local setup
-----------
1. Copy this folder into XAMPP's htdocs directory.
2. Create a MySQL database named unique_id_generator.
3. Import unique_id_generator.sql into that database using phpMyAdmin.
4. Update db_config.php if your local MySQL credentials are different.
5. Start Apache and MySQL, then open:
   http://localhost/oop-unique-id-generator-main/

Shared hosting / cPanel setup
-----------------------------
1. In cPanel, create a MySQL database and a database user. Assign the user
   to the database with All Privileges. cPanel often prefixes names with
   your account name, so copy the exact generated values.
2. Open phpMyAdmin in cPanel, select the new database, and import
   unique_id_generator.sql.
3. Upload all project files, including the hidden .htaccess file, into
   public_html (or a subfolder inside it). Keep the files together.
4. Open db_config.php in cPanel File Manager and set:
      host: usually localhost
      db: the full cPanel database name
      user: the full cPanel database username
      pass: the database user's password
5. Visit your domain or the uploaded subfolder URL.

Security
--------
- Do not use the MySQL root account on hosting.
- Do not share db_config.php or its password.
- The included .htaccess prevents direct web access to db_config.php.
- If your host supports environment variables, set DB_HOST, DB_NAME,
  DB_USER, and DB_PASS instead of storing credentials in the file.

Troubleshooting
---------------
- A database connection error usually means the database name, username,
  password, or host is incorrect. Check the exact cPanel-prefixed values.
- If the page downloads instead of running, PHP is not enabled for the domain.
- If PDO errors mention MySQL, ask the host to enable the pdo_mysql extension.
