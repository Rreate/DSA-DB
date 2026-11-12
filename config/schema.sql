-- Run ONCE by install.php with an admin MySQL account.
-- The running app uses a limited account and never executes this file.

CREATE TABLE IF NOT EXISTS `categories` (
  `cat_id` BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
  `category` VARCHAR(30) NOT NULL,
  PRIMARY KEY (`cat_id`),
  UNIQUE KEY `uq_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `user_id` BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
  `username` VARCHAR(50) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `failed_logins` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` DATETIME NULL,
  `created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `archives` (
  `archive_id` BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `file_type` VARCHAR(10) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_size` INT UNSIGNED NOT NULL,
  `cat_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`archive_id`),
  KEY `idx_archives_user_time` (`user_id`, `time`),
  KEY `idx_archives_user_type` (`user_id`, `file_type`),
  KEY `idx_archives_cat_time`  (`cat_id`, `time`),
  -- RESTRICT (not CASCADE): a category/user can't be removed while it still
  -- owns files, so no file is ever left on disk without a database row.
  CONSTRAINT `fk_archives_cat`  FOREIGN KEY (`cat_id`)  REFERENCES `categories`(`cat_id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_archives_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
