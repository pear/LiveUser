<?php
// LiveUser: A framework for authentication and authorization in PHP applications
// Copyright (C) 2002-2003 Markus Wolff
//
// This library is free software; you can redistribute it and/or
// modify it under the terms of the GNU Lesser General Public
// License as published by the Free Software Foundation; either
// version 2.1 of the License, or (at your option) any later version.
//
// This library is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
// Lesser General Public License for more details.
//
// You should have received a copy of the GNU Lesser General Public
// License along with this library; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

$GLOBALS['_LiveUser']['tables'] = array(
    'perm_users' => array(
        'fields' => array(
            'perm_user_id' => 'seq',
            'auth_user_id' => true,
            'auth_container_name' => true,
            'perm_type' => false,
         ),
        'joins' => array(
            'userrights' => 'perm_user_id',
            'groupusers' => 'perm_user_id',
        ),
        'ids' => array(
            'perm_user_id'
        ),
    ),
    'userrights' => array(
        'fields' => array(
            'perm_user_id' => true,
            'right_id' => true,
            'right_level' => false,
        ),
        'joins' => array(
            'perm_users' => 'perm_user_id',
            'rights' => 'right_id',
        ),
    ),
    'rights' => array(
        'fields' => array(
            'right_id' => 'seq',
            'area_id' => false,
            'right_define_name' => false,
            'has_implied' => false,
            'has_level' => false,
        ),
        'joins' => array(
            'areas' => 'area_id',
            'userrights' => 'right_id',
            'grouprights' => 'right_id',
            'right_implied' => array(
                'right_id' => 'right_id',
                'right_id' => 'implied_right_id',
            ),
            'translations' => array(
                'right_id' => 'section_id',
                LIVEUSER_SECTION_RIGHT => 'section_type',
            ),
        ),
        'ids' => array(
            'right_id'
        ),
    ),
    'right_implied' => array(
        'fields' => array(
            'right_id' => true,
            'implied_right_id' => true,
        ),
        'joins' => array(
            'rights' => array(
                'right_id' => 'right_id',
                'implied_right_id' => 'right_id',
            ),
        ),
    ),
    'translations' => array(
        'fields' => array(
            'section_id' => true,
            'section_type' => true,
            'language_id' => true,
            'name' => false,
            'description' => false,
        ),
        'joins' => array(
            'rights' => array(
                'section_id' => 'right_id',
                'section_type' => LIVEUSER_SECTION_RIGHT,
            ),
            'areas' => array(
                'section_id' => 'area_id',
                'section_type' => LIVEUSER_SECTION_AREA,
            ),
            'applications' => array(
                 'section_id' => 'application_id',
                 'section_type' => LIVEUSER_SECTION_APPLICATION,
            ),
            'groups' => array(
                'section_id' => 'group_id',
                'section_type' => LIVEUSER_SECTION_GROUP,
            ),
        ),
        'ids' => array(
            'section_id',
            'section_type',
            'language_id',
        ),
    ),
    'areas' => array(
        'fields' => array(
            'area_id' => 'seq',
            'application_id' => false,
            'area_define_name' => false,
        ),
        'joins' => array(
            'rights' => 'area_id',
            'applications' => 'application_id',
            'translations' => array(
                'area_id' => 'section_id',
                LIVEUSER_SECTION_AREA => 'section_type',
            ),
        ),
        'ids' => array(
            'area_id',
        ),
    ),
    'applications' => array(
        'fields' => array(
            'application_id' => 'seq',
            'application_define_name' => false,
        ),
        'joins' => array(
            'areas' => 'application_id',
            'translations' => array(
                'application_id' => 'section_id',
                LIVEUSER_SECTION_APPLICATION => 'section_type',
            ),
        ),
        'ids' => array(
            'application_id',
        ),
    ),
    'groups' => array(
        'fields' => array(
            'group_id' => 'seq',
            'group_type' => false,
            'group_define_name' => false,
            'is_active' => false,
            'owner_user_id' => false,
            'owner_group_id' => false,
        ),
        'joins' => array(
            'groupusers' => 'group_id',
            'grouprights' => 'group_id',
            'group_subgroups' => 'subgroup_id',
            'translations' => array(
                'group_id' => 'section_id',
                LIVEUSER_SECTION_GROUP => 'section_type',
            ),
        ),
        'ids' => array(
            'group_id',
        ),
    ),
    'groupusers' => array(
        'fields' => array(
            'perm_user_id' => true,
            'group_id' => true,
        ),
        'joins' => array(
            'groups' => 'group_id',
            'perm_users' => 'perm_user_id',
        ),
    ),
    'grouprights' => array(
        'fields' => array(
            'group_id' => true,
            'right_id' => true,
            'right_level' => false,
        ),
        'joins' => array(
            'rights' => 'right_id',
            'groups' => 'group_id',
        ),
    ),
    'group_subgroups' => array(
        'fields' => array(
            'group_id' => true,
            'subgroup_id' => true,
        ),
        'joins' => array(
            'groups' => 'group_id',
        ),
    ),
);

$GLOBALS['_LiveUser']['fields'] = array(
    'perm_user_id' => 'integer',
    'auth_user_id' => 'text',
    'auth_container_name' => 'text',
    'perm_type' => 'integer',
    'right_id' => 'integer',
    'right_level' => 'integer',
    'area_id' => 'integer',
    'application_id' => 'integer',
    'right_define_name' => 'text',
    'area_define_name' => 'text',
    'application_define_name' => 'text',
    'section_id' => 'integer',
    'section_type' => 'integer',
    'name' => 'text',
    'description' => 'text',
    'language_id' => 'text',
    'group_id' => 'integer',
    'group_type' => 'integer',
    'group_define_name' => 'text',
    'is_active' => 'boolean',
    'owner_user_id' => 'integer',
    'owner_group_id' => 'integer',
    'has_implied' => 'boolean',
    'has_level' => 'boolean',
    'implied_right_id' => 'integer',
    'subgroup_id' => 'integer'
);

$GLOBALS['_LiveUser']['alias'] = array(
    'perm_user_id' => 'perm_user_id',
    'auth_user_id' => 'auth_user_id',
    'auth_container_name' => 'auth_container_name',
    'perm_type' => 'perm_type',
    'right_id' => 'right_id',
    'right_level' => 'right_level',
    'area_id' => 'area_id',
    'application_id' => 'application_id',
    'right_define_name' => 'right_define_name',
    'area_define_name' => 'area_define_name',
    'application_define_name' => 'application_define_name',
    'section_id' => 'section_id',
    'section_type' => 'section_type',
    'name' => 'name',
    'description' => 'description',
    'language_id' => 'language_id',
    'group_id' => 'group_id',
    'group_type' => 'group_type',
    'group_define_name' => 'group_define_name',
    'is_active' => 'is_active',
    'owner_user_id' => 'owner_user_id',
    'owner_group_id' => 'owner_group_id',
    'has_implied' => 'has_implied',
    'has_level' => 'has_level',
    'implied_right_id' => 'implied_right_id',
    'subgroup_id' => 'subgroup_id',
);

?>