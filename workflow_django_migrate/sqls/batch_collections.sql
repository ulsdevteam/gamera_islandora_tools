CREATE TABLE `batch_collections` (
	`batch_id` INT(11) NOT NULL,
	`collection_id` INT(11) NOT NULL,
	`date_modified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`batch_id`, `collection_id`)
)
