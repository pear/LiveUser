# $Id$

#
# Table structure for table 'liveuser_applications'
#

CREATE TABLE `liveuser_applications` (
  `application_id` int(11) unsigned NOT NULL default '0',
  `application_define_name` varchar(32) NOT NULL default '',
  PRIMARY KEY  (`application_id`),
  UNIQUE KEY `application_define_name` (`application_define_name`)
);


#
# Table structure for table 'liveuser_areas'
#

CREATE TABLE `liveuser_areas` (
  `area_id` int(11) unsigned NOT NULL default '0',
  `application_id` int(11) unsigned NOT NULL default '0',
  `area_define_name` varchar(32) NOT NULL default '',
  PRIMARY KEY  (`area_id`),
  UNIQUE KEY `area_define_name` (`application_id`, `area_define_name`),
  KEY `areas_application_id` (`application_id`)
);



#
# Table structure for table 'liveuser_perm_users'
#

CREATE TABLE `liveuser_perm_users` (
  `perm_user_id` int(11) unsigned NOT NULL default '0',
  `auth_user_id` varchar(32) NOT NULL default '0',
  `perm_type` tinyint(3) unsigned default NULL,
  `auth_container_name` varchar(32) NOT NULL default '',
  PRIMARY KEY  (`perm_user_id`)
);



#
# Table structure for table 'liveuser_rights'
#

CREATE TABLE `liveuser_rights` (
  `right_id` int(11) unsigned NOT NULL default '0',
  `area_id` int(11) unsigned NOT NULL default '0',
  `right_define_name` varchar(32) NOT NULL default '',
  `has_implied` char(1) NOT NULL default 'N',
  PRIMARY KEY  (`right_id`),
  UNIQUE KEY `right_define_name` (`area_id`, `right_define_name`),
  KEY `rights_area_id` (`area_id`)
);




#
# Table structure for table 'liveuser_userrights'
#

CREATE TABLE `liveuser_userrights` (
  `perm_user_id` int(11) unsigned NOT NULL default '0',
  `right_id` int(11) unsigned NOT NULL default '0',
  `right_level` tinyint(3) default '3',
  PRIMARY KEY  (`right_id`,`perm_user_id`),
  KEY `perm_user_id` (`perm_user_id`),
  KEY `right_id` (`right_id`)
);

