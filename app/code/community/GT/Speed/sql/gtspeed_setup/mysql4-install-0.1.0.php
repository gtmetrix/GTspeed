<?php
 
$installer = $this;
 
$installer->startSetup();
 
$installer->run("
 
-- DROP TABLE IF EXISTS {$this->getTable('gtspeed/image')};
CREATE TABLE {$this->getTable('gtspeed/image')} (
  `image_id` varchar(32) NOT NULL default '',
  `filepath` varchar( 255 ) NOT NULL default '',
  `lastoptmtime` int(11) unsigned NOT NULL default '0',
  `optimized` tinyint(1) NOT NULL default '0',
  `ofilesize` int(11) unsigned NOT NULL default '0',
  `nfilesize` int(11) unsigned NOT NULL default '0',
  PRIMARY KEY (`image_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
 
-- DROP TABLE IF EXISTS {$this->getTable('gtspeed/stat')};
CREATE TABLE {$this->getTable('gtspeed/stat')} (
  `stat_id` smallint(5) unsigned NOT NULL auto_increment,
  `stat_name` varchar( 255 ) NOT NULL default 'general',
  `stat` int(11) unsigned NOT NULL default '0',
  PRIMARY KEY (`stat_id`),
  UNIQUE KEY `stat_name` (`stat_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
 
$installer->endSetup();
