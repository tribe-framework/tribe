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
  ADD INDEX(`id`); 

ALTER TABLE `data`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `data`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

CREATE TABLE `trac` (
  `id` bigint(20) NOT NULL,
  `visit` json DEFAULT NULL,
  `created_on` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `trac`
  ADD INDEX(`id`); 

ALTER TABLE `trac`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `trac`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

COMMIT;