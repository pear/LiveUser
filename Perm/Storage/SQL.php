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
     * Columns of the perm table.
     * Associative array with the names of the perm table columns.
     * The 'group_id' and 'group_define_name' fields have to be set.
     * 'group_type', 'is_active', 'owner_user_id' and 'owner_group_id' are optional.
     * It doesn't make sense to set only one of the time columns without the
     * other.
     *
     * The type attribute is only useful when using MDB or MDB2.
     *
     * @access public
     * @var    array
     */
    var $groupTableCols = array(
        'required' => array(
            'group_id' => array('type' => 'integer', 'name' => 'group_id'),
            'group_define_name' => array('type' => 'text', 'name' => 'group_define_name')
        ),
        'optional' => array(
            'group_type'    => array('type' => 'integer', 'name' => 'group_type'),
            'is_active'    => array('type' => 'boolean', 'name' => 'is_active'),
            'owner_user_id'  => array('type' => 'integer', 'name' => 'owner_user_id'),
            'owner_group_id' => array('type' => 'integer', 'name' => 'owner_group_id')
        )
    );

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
    }

    /**
     *
     *
     * @access public
     * @param int $uid
     * @param string $containerName
     * @return mixed array or false on failure
     */
    function mapUser($uid, $containerName)
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