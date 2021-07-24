SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `data` (
  `id` bigint(20) NOT NULL,
  `content` json DEFAULT NULL,
  `updated_on` bigint(20) DEFAULT NULL,
  `created_on` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `data`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `data`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE `data` ADD `user_id` VARCHAR(6) AS (`content`->>'$.user_id') VIRTUAL NULL AFTER `created_on`, ADD INDEX (`user_id`);
ALTER TABLE `data` ADD `role_slug` VARCHAR(100) AS (`content`->>'$.role_slug') VIRTUAL NULL AFTER `user_id`;
ALTER TABLE `data` ADD `slug` VARCHAR(255) AS (`content`->>'$.slug') VIRTUAL NULL AFTER `role_slug`, ADD INDEX (`slug`);
ALTER TABLE `data` ADD `content_privacy` VARCHAR(100) AS (`content`->>'$.content_privacy') VIRTUAL NULL AFTER `slug`;
ALTER TABLE `data` ADD `type` VARCHAR(100) AS (`content`->>'$.type') VIRTUAL NULL AFTER `content_privacy`;

CREATE TABLE `trac` (
  `id` bigint(20) NOT NULL,
  `visit` json DEFAULT NULL,
  `created_on` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `trac`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `trac`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

COMMIT;