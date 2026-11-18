-- student-database-crud-app schema
-- If your hosting panel already created a database, select it before import.
-- CREATE DATABASE your_db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE your_db_name;

CREATE TABLE IF NOT EXISTS studentclass (
  cid INT UNSIGNED NOT NULL AUTO_INCREMENT,
  cname VARCHAR(100) NOT NULL,
  PRIMARY KEY (cid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS student (
  sid INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sname VARCHAR(100) NOT NULL,
  sadress VARCHAR(255) NOT NULL,
  sclass INT UNSIGNED NOT NULL,
  sphone VARCHAR(20) NOT NULL,
  sresult VARCHAR(50) NOT NULL DEFAULT '',
  sattendance VARCHAR(20) NOT NULL DEFAULT '',
  PRIMARY KEY (sid),
  KEY idx_student_class (sclass),
  CONSTRAINT fk_student_class FOREIGN KEY (sclass) REFERENCES studentclass (cid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO studentclass (cname) VALUES
('Class 1'),
('Class 2'),
('Class 3');
