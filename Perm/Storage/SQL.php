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

/**
 * MDB2_Complex container for permission handling
 *
 * @package  LiveUser
 * @category authentication
 */

/**
 * Require parent class definition.
 */
require_once 'LiveUser/Perm/Storage.php';

/**
 * This is a PEAR::MDB2 backend driver for the LiveUser class.
 * A PEAR::MDB2 connection object can be passed to the constructor to reuse an
 * existing connection. Alternatively, a DSN can be passed to open a new one.
 *
 * Requirements:
 * - File "Liveuser.php" (contains the parent class "LiveUser")
 * - Array of connection options or a PEAR::MDB2 connection object must be
 *   passed to the constructor.
 *   Example: array('dsn' => 'mysql://user:pass@host/db_name')
 *              OR
 *            &$conn (PEAR::MDB2 connection object)
 *
 * @author  Lukas Smith <smith@backendmedia.com>
 * @author  Bjoern Kraus <krausbn@php.net>
 * @version $Id$
 * @package LiveUser
 * @category authentication
 */
class LiveUser_Perm_Storage_SQL extends LiveUser_Perm_Storage
{
    /**
     * dsn that was connected to
     * @var object
     * @access private
     */
    var $dsn = null;

    /**
     * PEAR::MDB2 connection object.
     *
     * @var    object
     * @access private
     */
    var $dbc = null;

    /**
     * Table prefix
     * Prefix for all db tables the container has.
     *
     * @var    string
     * @access public
     */
    var $prefix = 'liveuser_';

    /**
     *
     * @access public
     * @var    array
     */
    var $tables = array();

    /**
     *
     * @access public
     * @var    array
     */
    var $fields = array();

    /**
     *
     * @access public
     * @var    array
     */
    var $alias = array();

    /**
     * Constructor
     *
     * @access protected
     * @param  mixed      configuration array
     * @return void
     */
    function LiveUser_Perm_Storage_MDB2(&$confArray, &$storageConf)
    {
        $this->LiveUser_Perm_Storage($confArray, $storageConf);

        $this->tables = LiveUser::arrayMergeClobber(LiveUser_Perm_Simple::getTableDefaults(), $this->tables);
        $this->fields = LiveUser::arrayMergeClobber(LiveUser_Perm_Simple::getFieldDefaults(), $this->fields);
        $this->alias = LiveUser::arrayMergeClobber(LiveUser_Perm_Simple::getAliasDefaults(), $this->alias);
    }

    /**
     * Get the alias name if available
     */
    function getAlias($name)
    {
        if (isset($this->alias[$name])) {
            return $this->alias[$name];
        }
        return $name;
    }

    function getTableDefaults()
    {
        return array(
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
                'id' => 'perm_user_id',
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
                ),
                'joins' => array(
                    'areas' => 'area_id',
                    'userrights' => 'right_id',
                    'grouprights' => 'right_id',
                    'rights_implied' => array(
                        'right_id' => 'right_id',
                        'right_id' => 'implied_right_id',
                    ),
                    'translations' => array(
                        'right_id' => 'section_id',
                        LIVEUSER_SECTION_RIGHT => 'section_type',
                    ),
                ),
                'id' => 'right_id',
            ),
            'rights_implied' => array(
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
                'id' => 'area_id',
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
                'id' => 'application_id',
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
                    'translations' => array(
                        'group_id' => 'section_id',
                        LIVEUSER_SECTION_GROUP => 'section_type',
                    ),
                ),
                'id' => 'group_id',
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
        );
    }

    function getFieldDefaults()
    {
        return array(
            'perm_user_id' => 'integer',
            'auth_user_id' => 'integer',
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
            'group_id' => 'integer',
            'group_type' => 'integer',
            'group_define_name' => 'text',
            'is_active' => 'boolean',
            'owner_user_id' => 'integer',
            'owner_group_id' => 'integer',
            'implied_right_id' => 'integer',
        );
    }

    /**
     *
     *
     * @access public
     * @param int $authUserId
     * @param string $containerName
     * @return mixed array or false on failure
     */
    function mapUser($authUserId, $containerName)
    {
    }

    /**
     * Reads all rights of current user into a
     * two-dimensional associative array, having the
     * area names as the key of the 1st dimension.
     * Group rights and invididual rights are being merged
     * in the process.
     *
     * @access public
     * @param int $permUserId
     * @return mixed array of false on failure
     */
    function readUserRights($permUserId)
    {
    }

    /**
     * properly disconnect from resources
     *
     * @access  public
     */
    function disconnect()
    {
    }
}
?>