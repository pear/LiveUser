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
class LiveUser_Perm_Storage
{
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
    function LiveUser_Perm_Storage(&$confArray, &$storageConf)
    {
        $this->_stack = &PEAR_ErrorStack::singleton('LiveUser');
        if (is_array($storageConf)) {
            foreach ($storageConf as $key => $value) {
                if (isset($this->$key)) {
                    $this->$key =& $storageConf[$key];
                }
            }
        }
    }

    function mapUser($uid, $containerName)
    {
    }

    /**
     * Reads all rights of current user into a
     * two-dimensional associative array, having the
     * area names as the key of the 1st dimension.
     * Group rights and invididual rights are being merged
     * in the process.
     */
    function readUserRights($permUserId)
    {
    }

    function readAreaAdminAreas($permUserId)
    {
    }

    /**
     * Reads all the group ids in that the user is also a member of
     * (all groups that are subgroups of these are also added recursively)
     *
     * @access private
     * @see    readRights()
     * @return void
     */
    function readGroups($permUserId)
    {
    } // end func readGroups

    /**
     * Reads the group rights
     * and put them in the array
     *
     * right => 1
     *
     * @access  public
     * @return  mixed   MDB2_Error on failure or nothing
     */
    function readGroupRights($groupIds)
    {
    } // end func readGroupRights

    /**
     * store all properties in an array
     *
     * @access  public
     * @return  array containing the property values
     */
    function freeze($sessionName, $propertyValues)
    {
        $_SESSION[$sessionName]['perm'] = $propertyValues;
        return $propertyValues;
    } // end func freeze

    /**
     * Reinitializes properties
     *
     * @access  public
     * @param   array  $propertyValues
     */
    function unfreeze($sessionName)
    {
        return (isset($_SESSION[$sessionName]['perm']))
            ? $_SESSION[$sessionName]['perm'] : array();
    } // end func unfreeze

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