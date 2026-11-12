-- Runs only when the MariaDB volume is created for the first time.
-- The tests use a separate database (DATABASE_URL + "_test" suffix in the test environment).
CREATE DATABASE IF NOT EXISTS `zanimalnik_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON `zanimalnik_test`.* TO 'zanimalnik'@'%';
FLUSH PRIVILEGES;
