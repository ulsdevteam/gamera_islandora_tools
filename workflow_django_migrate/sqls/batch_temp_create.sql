DROP TABLE IF EXISTS `batch_temp`;

CREATE TABLE `batch_temp` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(64) NOT NULL default '',
  `collection_id` int(11) NOT NULL default '0',
  `type_id` int(11) NOT NULL default '0',
  `property_owner_id` int(11) default '0',
  `description` longtext NOT NULL,
  `sequence_id` int(11) NOT NULL default '0',
  `item_count` int(11) default '0',
  `date` date NOT NULL default '0000-00-00',
  `active` tinyint(1) NOT NULL default '0',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `name` (`name`)
);
