ALTER TABLE `collection`
	ADD COLUMN `PID` VARCHAR(64) NOT NULL DEFAULT '' AFTER `name`,
	ADD COLUMN `date_modified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `PID`;
