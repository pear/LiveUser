# $Id$


#
# Table structure for table 'liveuser_grouprights'
#

CREATE TABLE `liveuser_grouprights` (
  `group_id` int(11) unsigned NOT NULL default '0',
  `right_id` int(11) unsigned NOT NULL default '0',
  `right_level` tinyint(3) unsigned default '3',
  PRIMARY KEY  (`group_id`,`right_id`)
);



#
# Table structure for table 'liveuser_groups'
#

CREATE TABLE `liveuser_groups` (
  `group_id` int(11) unsigned NOT NULL default '0',
  `group_type` int(11) unsigned NULL default '1',
  `group_define_name` varchar(32) default NULL,
  `owner_user_id` int(11) unsigned default NULL,
  `owner_group_id` int(11) unsigned default NULL,
  `is_active` char(1) NOT NULL default 'N',
  PRIMARY KEY  (`group_id`),
  UNIQUE KEY `group_define_name` (`group_define_name`)
);



#
# Table structure for table 'liveuser_groupusers'
#

CREATE TABLE `liveuser_groupusers` (
  `perm_user_id` int(11) unsigned NOT NULL default '0',
  `group_id` int(11) unsigned NOT NULL default '0',
  PRIMARY KEY  (`group_id`,`perm_user_id`),
  KEY `perm_user_id` (`perm_user_id`),
  KEY `group_id` (`group_id`)
);