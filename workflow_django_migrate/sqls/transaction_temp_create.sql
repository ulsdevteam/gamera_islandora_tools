DROP TABLE IF EXISTS `transaction_temp`;

CREATE TABLE `transaction_temp` (
  `id` int(11) NOT NULL auto_increment,
  `item_id` int(11) NOT NULL default '0',
  `user_id` int(11) NOT NULL default '0',
  `description` longtext NOT NULL,
  `timestamp` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`),
);
