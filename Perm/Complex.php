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
require_once 'LiveUser/Perm/Medium.php';

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
class LiveUser_Perm_Complex extends LiveUser_Perm_Medium
{
    /**
     * Reads all individual implied rights of current user into
     * an array of this format:
     * RightName -> Value
     *
     * @access private
     * @param array $rightIds
     * @param string $table
     * @return array with rightIds as key and level as value
     */
    function _readImpliedRights($rightIds, $table)
    {
        if (!is_array($rightIds) || !count($rightIds)) {
            return null;
        }

        $queue = array();
        $result = $this->_storage->readImplyingRights($rightIds, $table);

        if (!is_array($result) || !count($result)) {
            return false;
        }
        $queue = $result;

        while (count($queue)) {
            $currentRights = reset($queue);
            $currentLevel = key($queue);
            unset($queue[$currentLevel]);

            $result = $this->_storage->readImpliedRights($currentRights, $currentLevel);
            if (!is_array($result)) {
                return false;
            }
            foreach ($result as $val) {
                // only store the implied right if the right wasn't stored before
                // or if the level is higher
                if (!isset($rightIds[$val['right_id']]) ||
                    $rightIds[$val['right_id']] < $val['right_level'])
                {
                    $rightIds[$val['right_id']] = $val['right_level'];
                    if ($val['has_implied']) {
                        $queue[$val['right_level']][] = $val['right_id'];
                    }
                }
            }
        }
        return $rightIds;
    } // end func _readImpliedRights

    /**
     * Reads all individual rights of current user into
     * an array of this format:
     * RightName -> Value
     *
     * @access private
     * @param int $permUserId
     * @see    readRights()
     * @return void
     */
    function readUserRights($permUserId)
    {
        $userRights = parent::readUserRights($permUserId);
        $result = $this->_readImpliedRights($userRights, 'user');
        if ($result) {
            $this->userRights = array_merge($this->userRights, $result);
        }
        return $this->userRights;
    } // end func readUserRights

    /**
     * Reads all the group ids in that the user is also a member of
     * (all groups that are subgroups of these are also added recursively)
     *
     * @access private
     * @param int $permUserId
     * @see    readRights()
     * @return void
     */
    function readGroups($permUserId)
    {
        $result = parent::readGroups($permUserId);

        // get all subgroups recursively
        while (count($result)) {
            $result = $this->readSubGroups($this->groupIds, $result);
            if (is_array($result)) {
                $this->groupIds = array_merge($result, $this->groupIds);
            }
        }
        return $this->groupIds;
    } // end func readGroups

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
        $result = $this->_storage->readSubGroups($groupIds, $newGroupIds);
        if ($result === false) {
            return false;
        }
        return $result;
    }

    /**
     * Reads all individual rights of current user into
     * a two-dimensional array of this format:
     * "GroupName" => "RightName" -> "Level"
     *
     * @access private
     * @param   array $groupIds array with id's for the groups
     *                          that rights will be read from
     * @see    readRights()
     * @return void
     */
    function readGroupRights($groupIds)
    {
        $groupRights = parent::readGroupRights($groupIds);

        $result = $this->_readImpliedRights($groupRights, 'group');
        if ($result) {
            $this->groupRights = $result;
        }
        return $this->groupRights;
    } // end func readGroupRights

    /**
     * Checks if the current user has a certain right in a
     * given area at the necessary level.
     *
     * Level 1: requires that owner_user_id matches $this->permUserId
     * Level 2: requires that the $owner_group_id matches the id one of
     *          the (sub)groups that $this->permUserId is a memember of
     *          or requires that the $owner_user_id matches a perm_user_id of
     *          a memeber of one of $this->permUserId's (sub)groups
     * Level 3: no requirements
     *
     * Important note:
     *          Every ressource MAY be owned by a user and/or by a group.
     *          Therefore, $owner_user_id and/or $owner_group_id can
     *          either be an integer or null.
     *
     * @access private
     * @see    checkRightLevel()
     * @param  integer  $level          Level value as returned by checkRight().
     * @param  mixed  $owner_user_id  Id or array of Ids of the owner of the
                                        ressource for which the right is requested.
     * @param  mixed  $owner_group_id Id or array of Ids of the group of the
     *                                  ressource for which the right is requested.
     * @return boolean  level if the level is sufficient to grant access else false.
     */
    function checkLevel($level, $owner_user_id, $owner_group_id)
    {
        // level above 0
        if ($level <= 0) {
            return false;
        }
        // highest level (that is level 3)
        if ($level == LIVEUSER_MAX_LEVEL) {
            return $level;
        }
        // level 1 or higher
        if ((!is_array($owner_user_id) && $this->permUserId == $owner_user_id) ||
            is_array($owner_user_id) && in_array($this->permUserId, $owner_user_id))
        {
            return $level;
        // level 2 or higher
        }
        if ($level >= 2) {
            // check if the ressource is owned by a (sub)group
            // that the user is part of
            if (is_array($owner_group_id)) {
                if (count(array_intersect($owner_group_id, $this->groupIds))) {
                    return $level;
                }
            } elseif (in_array($owner_group_id, $this->groupIds)) {
                return $level;
            }
        }
        return false;
    } // end func checkLevel

    /**
     *
     *
     * @access public
     * @param int $permUserId
     * @return mixed array or false on failure
     */
    function readAreaAdminAreas($permUserId)
    {
        $this->userRights = array();

        $result = $this->_storage->readAreaAdminAreas($permUserId);
        if ($result === false) {
            return false;
        }

        $this->areaAdminAreas = $result;
        return $this->areaAdminAreas;
    }
}
?>