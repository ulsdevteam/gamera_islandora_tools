ALTER TABLE `content_types`
	ADD COLUMN `fedora_model_name` VARCHAR(50) NULL AFTER `content_name`;

UPDATE `islandora_workflow`.`content_types` SET `fedora_model_name`='manuscriptCModel' WHERE  `content_type_id`=3;
UPDATE `islandora_workflow`.`content_types` SET `fedora_model_name`='bookCModel' WHERE  `content_type_id`=1;
UPDATE `islandora_workflow`.`content_types` SET `fedora_model_name`='sp_large_image_cmodel' WHERE  `content_type_id`=2;
UPDATE `islandora_workflow`.`content_types` SET `fedora_model_name`='sp_large_image_cmodel' WHERE  `content_type_id`=4;
UPDATE `islandora_workflow`.`content_types` SET `fedora_model_name`='newspaperIssueCModel' WHERE  `content_type_id`=5;
UPDATE `islandora_workflow`.`content_types` SET `fedora_model_name`='sp-audioCModel' WHERE  `content_type_id`=6;
UPDATE `islandora_workflow`.`content_types` SET `fedora_model_name`='sp_videoCModel' WHERE  `content_type_id`=7;
