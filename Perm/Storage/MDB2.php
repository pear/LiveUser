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
require_once 'LiveUser/Perm/Storage/SQL.php';
require_once 'MDB2.php';

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
class LiveUser_Perm_Storage_MDB2 extends LiveUser_Perm_Storage_SQL
{
    /**
     * Constructor
     *
     * @access protected
     * @param  mixed      configuration array
     * @return void
     */
    function LiveUser_Perm_Storage_MDB2(&$confArray, &$storageConf)
    {
        $this->LiveUser_Perm_Storage_SQL($confArray, $storageConf);
        if (isset($storageConf['connection']) &&
                MDB2::isConnection($storageConf['connection'])
        ) {
            $this->dbc = &$storageConf['connection'];
        } elseif (isset($storageConf['dsn'])) {
            $this->dsn = $storageConf['dsn'];
            $function = null;
            if (isset($storageConf['function'])) {
                $function = $storageConf['function'];
            }
            $options = null;
            if (isset($storageConf['options'])) {
                $options = $storageConf['options'];
            }
            $options['portability'] = MDB2_PORTABILITY_ALL;
            if ($function == 'singleton') {
                $this->dbc =& MDB2::singleton($storageConf['dsn'], $options);
            } else {
                $this->dbc =& MDB2::connect($storageConf['dsn'], $options);
            }
            if (PEAR::isError($this->dbc)) {
                return false;
            }
        }
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
        $query = '
            SELECT
                LU.perm_user_id AS userid,
                LU.perm_type    AS usertype
            FROM
                '.$this->prefix.'perm_users LU
            WHERE
                auth_user_id = '.$this->dbc->quote($uid, 'text').'
            AND
                auth_container_name = '.$this->dbc->quote($containerName, 'text');

        $result = $this->dbc->queryRow($query, array('integer', 'integer'), MDB2_FETCHMODE_ASSOC);

        if (PEAR::isError($result)) {
            return false;
        }

        return $result;
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
        $query = '
            SELECT
                R.right_id,
                U.right_level
            FROM
                '.$this->prefix.'rights R,
                '.$this->prefix.'userrights U
            WHERE
                R.right_id = U.right_id
            AND
                U.perm_user_id = '.$this->dbc->quote($permUserId, 'integer');

        $types = array('integer', 'integer');
        $result = $this->dbc->queryAll($query, $types, MDB2_FETCHMODE_ORDERED, true);

        if (PEAR::isError($result)) {
            return false;
        }

        return $result;
    }

    /**
     *
     *
     * @access public
     * @param int $permUserId
     * @return mixed array or false on failure
     */
    function readAreaAdminAreas($permUserId)
    {
        // get all areas in which the user is area admin
        $query = '
            SELECT
                R.right_id,
                '.LIVEUSER_MAX_LEVEL.' AS right_level
            FROM
                '.$this->prefix.'area_admin_areas AAA,
                '.$this->prefix.'rights R
            WHERE
                AAA.area_id = R.area_id
            AND
                AAA.perm_user_id = '.$this->dbc->quote($permUserId, 'integer');

        $types = array('integer', 'integer');
        $result = $this->dbc->queryAll($query, $types, MDB2_FETCHMODE_ORDERED, true);

        if (PEAR::isError($result)) {
            return false;
        }

        return $result;
    }

    /**
     * Reads all the group ids in that the user is also a member of
     * (all groups that are subgroups of these are also added recursively)
     *
     * @access private
     * @param int $permUserId
     * @see    readRights()
     * @return mixed array or false on failure
     */
    function readGroups($permUserId)
    {
        $query = '
            SELECT
                GU.group_id
            FROM
                '.$this->prefix.'groupusers GU,
                '.$this->prefix.'groups G
            WHERE
                GU.group_id = G. group_id
            AND
                G.is_active = '.$this->dbc->quote(true, 'boolean').'
            AND
                perm_user_id = '.$this->dbc->quote($permUserId, 'integer');

        $result = $this->dbc->queryCol($query, $this->groupTableCols['required']['group_id']['type']);

        if (PEAR::isError($result)) {
            return false;
        }

        return $result;
    } // end func readGroups

    /**
     * Reads the group rights
     * and put them in the array
     *
     * right => 1
     *
     * @access  public
     * @param   array $groupIds array with id's for the groups 
     *                          that rights will be read from
     * @return  mixed   array or false on failure
     */
    function readGroupRights($groupIds)
    {
        $query = '
            SELECT
                GR.right_id,
                MAX(GR.right_level)
            FROM
                '.$this->prefix.'grouprights GR
            WHERE
                GR.group_id IN('.$this->dbc->datatype->implodeArray($groupIds, 'integer').')
            GROUP BY
                GR.right_id';

        $types = array('integer', 'integer');
        $result = $this->dbc->queryAll($query, $types, MDB2_FETCHMODE_ORDERED, true);

        if (PEAR::isError($result)) {
            return false;
        }

        return $result;
    } // end func readGroupRights

    /**
     *
     *
     * @access public
     * @param array $groupIds
     * @param array $newGroupIds
     * @return mixed array or false on failure
     */
    function readSubGroups($groupIds, $newGroupIds)
    {
        $query = '
            SELECT
                DISTINCT SG.subgroup_id
            FROM
                '.$this->prefix.'groups G,
                '.$this->prefix.'group_subgroups SG
            WHERE
                SG.subgroup_id = G.' . $this->groupTableCols['required']['group_id']['name'] . '
            AND
                SG.group_id IN ('.$this->dbc->datatype->implodeArray($newGroupIds, $this->groupTableCols['required']['group_id']['type']).')
            AND
                SG.subgroup_id NOT IN ('.$this->dbc->datatype->implodeArray($groupIds, $this->groupTableCols['required']['group_id']['type']).')';

        if (isset($this->groupTableCols['optional']['is_active'])) {
            $query .= 'AND
                G.' . $this->groupTableCols['optional']['is_active']['name'] . '=' .
                    $this->dbc->quote(true, $this->groupTableCols['optional']['is_active']['type']);
        }

        $result = $this->dbc->queryCol($query, $this->groupTableCols['required']['group_id']['type']);

        if (PEAR::isError($result)) {
            return false;
        }

        return $result;
    }

    /**
     *
     *
     * @access public
     * @param array $rightsId
     * @param string $table
     * @return mixed array or false on failure
     */
    function readImplyingRights($rightIds, $table)
    {
        $query = '
            SELECT
            DISTINCT
                TR.right_level,
                TR.right_id
            FROM
                '.$this->prefix.'rights R,
                '.$this->prefix.$table.'rights TR
            WHERE
                TR.right_id = R.right_id
            AND
                R.right_id IN ('.$this->dbc->datatype->implodeArray(array_keys($rightIds), 'integer').')
            AND
                R.has_implied='.$this->dbc->quote(true, 'boolean');

        $types = array('integer', 'integer');
        $result = $this->dbc->queryAll($query, $types, MDB2_FETCHMODE_ORDERED, true, false, true);

        if (PEAR::isError($result)) {
            return false;
        }

        return $result;
    }

    /**
    *
    *
    * @access public
    * @param array $currentRights
    * @param string $currentLevel
    * @return mixed array or false on failure
    */
    function readImpliedRights($currentRights, $currentLevel)
    {
        $query = '
            SELECT
                RI.implied_right_id AS right_id,
                '.$currentLevel.' AS right_level,
                R.has_implied
            FROM
                '.$this->prefix.'rights R,
                '.$this->prefix.'right_implied RI
            WHERE
                RI.implied_right_id = R.right_id
            AND
                RI.right_id IN ('.$this->dbc->datatype->implodeArray($currentRights, 'integer').')';

        $types = array('integer', 'integer', 'boolean');
        $result = $this->dbc->queryAll($query, $types, MDB2_FETCHMODE_ASSOC);

        if (PEAR::isError($result)) {
            return false;
        }

        return $result;
    }
}
?>