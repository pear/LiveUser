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
 * Medium container for permission handling
 *
 * @package  LiveUser
 * @category authentication
 */

/**
 * Require parent class definition.
 */
require_once 'LiveUser/Perm/Simple.php';

/**
 * Medium permission complexity driver for LiveUser.
 *
 * Description:
 * The Medium provides the following functionalities
 * - users
 * - groups
 * - grouprights
 * - userrights
 * - authareas
 *
 * @author   Arnaud Limbourg
 * @version  $Id$
 * @package  LiveUser
 * @category authentication
 */
class LiveUser_Perm_Medium extends LiveUser_Perm_Simple
{
    /**
     * One-dimensional array containing all the groups
     * ids for the actual user.
     *
     * Format: "RightId" => "Level"
     *
     * @var array
     */
    var $groupIds = array();

    /**
     * One-dimensional array containing only the group
     * rights for the actual user.
     *
     * Format: "RightId" => "Level"
     *
     * @var array
     */
    var $groupRights = array();

    /**
     * Constructor
     *
     * @access protected
     * @param  mixed      configuration array
     * @return void
     */
    function LiveUser_Perm_Medium(&$confArray)
    {
        $this->LiveUser_Perm_Simple($confArray);
    }

    /**
     * Reads all rights of current user into an
     * associative array.
     * Group rights and invididual rights are being merged
     * in the process.
     *
     * @access private
     * @return void
     */
    function readRights()
    {
        $this->rights = array();

        $result = $this->readUserRights($this->permUserId);
        if ($result === false) {
            return false;
        }

        if ($this->userType == LIVEUSER_AREAADMIN_TYPE_ID) {
            $result = $this->readAreaAdminAreas($this->permUserId);
            if ($result === false) {
               return false;
            }

            if (is_array($this->areaAdminAreas)) {
                if (is_array($this->userRights)) {
                    $this->userRights = $this->areaAdminAreas + $this->userRights;
                } else {
                    $this->userRights = $this->areaAdminAreas;
                }
            }
        }

        $result = $this->readGroups($this->permUserId);
        if ($result === false) {
            return false;
        }

        $result = $this->readGroupRights($this->groupIds);
        if ($result === false) {
            return false;
        }

        $tmpRights = $this->groupRights;

        // Check if user has individual rights...
        if (is_array($this->userRights)) {
            // Overwrite values from temporary array with values from userrights
            foreach ($this->userRights as $right => $level) {
                if (isset($tmpRights[$right])) {
                    if ($level < 0) {
                        // Revoking rights: A negative value indicates that the
                        // right level is lowered or the right is even revoked
                        // despite the group memberships of this user
                        $tmpRights[$right] = $tmpRights[$right] + $level;
                    } else {
                        $tmpRights[$right] = max($tmpRights[$right], $level);
                    }
                } else {
                    $tmpRights[$right] = $level;
                }
            }
        }

        // Strip values from array if level is not greater than zero
        if (is_array($tmpRights)) {
            foreach ($tmpRights as $right => $level) {
               if ($level > 0) {
                   $this->rights[$right] = $level;
               }
            }
        }

        return $this->rights;
    } // end func readRights

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

    /**
     *
     *
     * @access public
     * @param int $permUserId
     * @return mixed array or false on failure
     */
    function readGroups($permUserId)
    {
        $this->groupIds = array();

        $result = $this->_storage->readGroups($permUserId);
        if ($result === false) {
            return false;
        }

        $this->groupIds = $result;
        return $this->groupIds;
    }

    /**
     *
     *
     * @access public
     * @param array $groupIds
     * @return mixed array or false on failure
     */
    function readGroupRights($groupIds)
    {
        $this->groupRights = array();

        if (!is_array($groupIds) || !count($groupIds)) {
            return null;
        }

        $result = $this->_storage->readGroupRights($groupIds);
        if ($result === false) {
            return false;
        }

        $this->groupRights = $result;
        return $this->groupRights;
    }

    /**
     * Checks if the current user is a member of a certain group
     * If $this->ondemand and $ondemand is true, the groups will be loaded on
     * the fly.
     *
     * @access  public
     * @param   integer $group_id  Id of the group to check for.
     * @param   boolean $ondemand  allow ondemand reading of groups
     * @return  boolean.
     */
    function checkGroup($group_id)
    {
        if (is_array($this->groupIds)) {
            return in_array($group_id, $this->groupIds);
        }
        return false;
    } // end func checkGroup
} // end class LiveUser_Perm_Container_MDB2_Medium
?>