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
 * DB_Complex container for permission handling
 *
 * @package  LiveUser
 * @category authentication
 */

/**
 * Require parent class definition.
 */
require_once 'LiveUser/Perm/Storage/SQL.php';
require_once 'DB.php';

/**
 * This is a PEAR::DB backend driver for the LiveUser class.
 * A PEAR::DB connection object can be passed to the constructor to reuse an
 * existing connection. Alternatively, a DSN can be passed to open a new one.
 *
 * Requirements:
 * - File "Liveuser.php" (contains the parent class "LiveUser")
 * - Array of connection options or a PEAR::DB connection object must be
 *   passed to the constructor.
 *   Example: array('dsn' => 'mysql://user:pass@host/db_name')
 *              OR
 *            &$conn (PEAR::DB connection object)
 *
 * @author  Lukas Smith <smith@backendmedia.com>
 * @author  Bjoern Kraus <krausbn@php.net>
 * @version $Id$
 * @package LiveUser
 * @category authentication
 */
class LiveUser_Perm_Storage_DB extends LiveUser_Perm_Storage_SQL
{
    /**
     * Constructor
     *
     * @access protected
     * @param  mixed      configuration array
     * @return void
     */
    function LiveUser_Perm_Storage_DB(&$confArray, &$storageConf)
    {
        $this->LiveUser_Perm_Storage_SQL($confArray, $storageConf);
        if (isset($storageConf['connection']) &&
                DB::isConnection($storageConf['connection'])
        ) {
            $this->dbc = &$storageConf['connection'];
        } elseif (isset($storageConf['dsn'])) {
            $this->dsn = $storageConf['dsn'];
            $options = null;
            if (isset($storageConf['options'])) {
                $options = $storageConf['options'];
            }
            $options['portability'] = DB_PORTABILITY_ALL;
            $this->dbc =& DB::connect($storageConf['dsn'], $options);
        }
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
        $query = '
            SELECT
                ' . $this->getAlias('perm_user_id') . ' AS perm_user_id,
                ' . $this->getAlias('perm_type') . '    AS perm_type
            FROM
                '.$this->prefix.'perm_users
            WHERE
                ' . $this->getAlias('auth_user_id') . ' = '.
                    $this->dbc->quoteSmart($authUserId).'
            AND
                ' . $this->getAlias('auth_container_name') . ' = '.
                    $this->dbc->quoteSmart((string)$containerName);

        $result = $this->dbc->getRow($query, null, DB_FETCHMODE_ASSOC);

        if (PEAR::isError($result)) {
            $this->_stack->push(LIVEUSER_ERROR, 'exception', array(),
                'error in query' . $result->getMessage . '-' . $result->getUserinfo());
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
                R.' . $this->getAlias('right_id') . ',
                U.' . $this->getAlias('right_level') . '
            FROM
                '.$this->prefix.'rights R,
                '.$this->prefix.'userrights U
            WHERE
                R.' . $this->getAlias('right_id') . ' = U.' . $this->getAlias('right_id') . '
            AND
                U.' . $this->getAlias('perm_user_id') . ' = '.
                    $this->dbc->quoteSmart($permUserId);

        $result = $this->dbc->getAssoc($query, false, null, DB_FETCHMODE_ORDERED);

        if (PEAR::isError($result)) {
            $this->_stack->push(LIVEUSER_ERROR, 'exception', array(),
                'error in query' . $result->getMessage . '-' . $result->getUserinfo());
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
                R.' . $this->getAlias('right_id') . ' AS right_id,
                '.LIVEUSER_MAX_LEVEL.'                AS right_level
            FROM
                '.$this->prefix.'area_admin_areas AAA,
                '.$this->prefix.'rights R
            WHERE
                AAA.area_id = R.area_id
            AND
                AAA.' . $this->getAlias('perm_user_id') . ' = '.
                    $this->dbc->quoteSmart($permUserId);

        $result = $this->dbc->getAssoc($query, false, null, DB_FETCHMODE_ORDERED);

        if (PEAR::isError($result)) {
            $this->_stack->push(LIVEUSER_ERROR, 'exception', array(),
                'error in query' . $result->getMessage . '-' . $result->getUserinfo());
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
                GU.' . $this->getAlias('group_id') . '
            FROM
                '.$this->prefix.'groupusers GU,
                '.$this->prefix.'groups G
            WHERE
                GU.' . $this->getAlias('group_id') . ' = G. ' . $this->getAlias('group_id') . '
            AND
                ' . $this->getAlias('perm_user_id') . ' = '.
                    $this->dbc->quoteSmart($permUserId);

        if (isset($this->tables['groups']['fields']['is_active'])) {
            $query .= 'AND
                G.' . $this->getAlias('is_active') . '=' .
                    $this->dbc->quoteSmart(true);
        }

        $result = $this->dbc->getCol($query);

        if (PEAR::isError($result)) {
            $this->_stack->push(LIVEUSER_ERROR, 'exception', array(),
                'error in query' . $result->getMessage . '-' . $result->getUserinfo());
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
                GR.' . $this->getAlias('right_id') . ',
                MAX(GR.' . $this->getAlias('right_level') . ')
            FROM
                '.$this->prefix.'grouprights GR
            WHERE
                GR.' . $this->getAlias('group_id') . ' IN('.
                    implode(', ', $groupIds).')
            GROUP BY
                GR.' . $this->getAlias('right_id') . '';

        $result = $this->dbc->getAssoc($query, false, null, DB_FETCHMODE_ORDERED);

        if (PEAR::isError($result)) {
            $this->_stack->push(LIVEUSER_ERROR, 'exception', array(),
                'error in query' . $result->getMessage . '-' . $result->getUserinfo());
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
                DISTINCT SG.' . $this->getAlias('subgroup_id') . '
            FROM
                '.$this->prefix.'groups G,
                '.$this->prefix.'group_subgroups SG
            WHERE
                SG.' . $this->getAlias('subgroup_id') . ' = G.' .
                    $this->getAlias('group_id') . '
            AND
                SG.' . $this->getAlias('group_id') . ' IN ('.
                    implode(', ', $newGroupIds).')
            AND
                SG.' . $this->getAlias('subgroup_id') . ' NOT IN ('.
                    implode(', ', $groupIds).')';

        if (isset($this->tables['groups']['fields']['is_active'])) {
            $query .= 'AND
                G.' . $this->getAlias('is_active') . '=' .
                    $this->dbc->quoteSmart('Y');
        }

        $result = $this->dbc->getCol($query);

        if (PEAR::isError($result)) {
            $this->_stack->push(LIVEUSER_ERROR, 'exception', array(),
                'error in query' . $result->getMessage . '-' . $result->getUserinfo());
            return false;
        }

        return $result;
    }

    /**
     *
     *
     * @access public
     * @param array $rightIds
     * @param string $table
     * @return mixed array or false on failure
     */
    function readImplyingRights($rightIds, $table)
    {
        $query = '
            SELECT
            DISTINCT
                TR.' . $this->getAlias('right_level') . ',
                TR.' . $this->getAlias('right_id') . '
            FROM
                '.$this->prefix.'rights R,
                '.$this->prefix.$table.'rights TR
            WHERE
                TR.' . $this->getAlias('right_id') . ' = R.' . $this->getAlias('right_id') . '
            AND
                R.' . $this->getAlias('right_id') . ' IN ('.
                    implode(', ', array_keys($rightIds)).')
            AND
                R.' . $this->getAlias('has_implied') . '='.
                    $this->dbc->quoteSmart('Y');

        $result = $this->dbc->getAssoc($query, false, null, DB_FETCHMODE_ORDERED, true);

        if (PEAR::isError($result)) {
            $this->_stack->push(LIVEUSER_ERROR, 'exception', array(),
                'error in query' . $result->getMessage . '-' . $result->getUserinfo());
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
                RI.' . $this->getAlias('implied_right_id') . ' AS right_id,
                '.$currentLevel.'                              AS right_level,
                R.' . $this->getAlias('has_implied') . '       AS has_implied
            FROM
                '.$this->prefix.'rights R,
                '.$this->prefix.'right_implied RI
            WHERE
                RI.' . $this->getAlias('implied_right_id') . ' = R.' . $this->getAlias('right_id') . '
            AND
                RI.' . $this->getAlias('right_id') . ' IN ('.
                    implode(', ', $currentRights).')';

        $result = $this->dbc->getAll($query, null, DB_FETCHMODE_ASSOC);

        if (PEAR::isError($result)) {
            $this->_stack->push(LIVEUSER_ERROR, 'exception', array(),
                'error in query' . $result->getMessage . '-' . $result->getUserinfo());
            return false;
        }

        for($i=0,$j=count($result); $i<$j; ++$i) {
            $result[$i]['has_implied'] = ($result[$i]['has_implied'] == 'Y');
        }

        return $result;
    }
}
?>