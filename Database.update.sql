CREATE TABLE IF NOT EXISTS `synchronization` (
  `syncid` int(11) NOT NULL AUTO_INCREMENT,
  `table_name` varchar(255) NOT NULL,
  `primarykey` varchar(255) NOT NULL,
  `lastkey_in` int(11) NOT NULL DEFAULT '0',
  `lastkey_out` int(11) NOT NULL DEFAULT '0'
  PRIMARY KEY (`syncid`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

