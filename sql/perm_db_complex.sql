# $Id$


#
# Table structure for table 'liveuser_area_admin_areas'
#

CREATE TABLE `liveuser_area_admin_areas` (
  `area_id` int(11) unsigned NOT NULL default '0',
  `perm_user_id` int(11) unsigned NOT NULL default '0',
  PRIMARY KEY  (`area_id`,`perm_user_id`),
  KEY `perm_user_id` (`perm_user_id`),
  KEY `area_id` (`area_id`)
);



#
# Table structure for table 'liveuser_group_subgroups'
#

CREATE TABLE `liveuser_group_subgroups` (
  `group_id` int(11) unsigned NOT NULL default '0',
  `subgroup_id` int(11) unsigned NOT NULL default '0',
  PRIMARY KEY  (`group_id`,`subgroup_id`),
  KEY `subgroup_id` (`subgroup_id`),
  KEY `group_id` (`group_id`)
);



#
# Table structure for table 'liveuser_right_implied'
#

CREATE TABLE `liveuser_right_implied` (
  `right_id` int(11) unsigned NOT NULL default '0',
  `implied_right_id` int(11) unsigned NOT NULL default '0',
  PRIMARY KEY  (`right_id`,`implied_right_id`),
  KEY `right_id` (`right_id`),
  KEY `implied_right_id` (`implied_right_id`)
);