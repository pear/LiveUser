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
 * Base class for permission handling
 *
 * @package  LiveUser
 * @category authentication
 */

/**
 * This class provides a set of functions for implementing a user
 * permission management system on live websites. All authorisation
 * backends/containers must be extensions of this base class.
 *
 * @author  Markus Wolff <wolff@21st.de>
 * @author  Bjoern Kraus <krausbn@php.net>
 * @version $Id$
 * @package LiveUser
 * @category authentication
 */
class LiveUser_Perm_Simple
{
    /**
     * Unique user ID, used to identify users from the auth container.
     *
     * @var string
     */
    var $permUserId = '';

    /**
     * One-dimensional array containing current user's rights.
     * This already includes grouprights and possible overrides by
     * individual right settings.
     *
     * Format: "RightId" => "Level"
     *
     * @var mixed
     */
    var $rights = false;

    /**
     * One-dimensional array containing only the individual
     * rights for the actual user.
     *
     * Format: "RightId" => "Level"
     *
     * @var array
     */
    var $userRights = array();

    /**
     * Defines the user type.
     *
     * @var integer
     */
    var $userType = LIVEUSER_ANONYMOUS_TYPE_ID;

    /**
     * Error stack
     *
     * @var PEAR_ErrorStack
     */
    var $_stack = null;

    /**
     * Class constructor. Feel free to override in backend subclasses.
     */
    function LiveUser_Perm_Simple(&$confArray)
    {
        $this->_stack = &PEAR_ErrorStack::singleton('LiveUser');
        $this->_storage = LiveUser::storageFactory($confArray);
        if (is_array($confArray)) {
            foreach ($confArray as $key => $value) {
                if (isset($this->$key)) {
                    $this->$key =& $confArray[$key];
                }
            }
        }
    }

    /**
     * Tries to find the user with the given user ID in the permissions
     * container. Will read all permission data and return true on success.
     *
     * @access  public
     * @param   string  user identifier
     * @param   string  name of the auth container
     * @return  boolean true on success or false on failure
     */
    function init($uid = null, $containerName = null)
    {
        $result = $this->_storage->mapUser($uid, $containerName);
        if ($result === false) {
            return false;
        }

        $this->permUserId = $result['userid'];
        $this->userType   = $result['usertype'];

        $this->readRights();

        return true;
    }

    /**
     * Reads all rights of current user into a
     * two-dimensional associative array, having the
     * area names as the key of the 1st dimension.
     * Group rights and invididual rights are being merged
     * in the process.
     *
     *
     * @access public
     * @return mixed array or false on failure
     */
    function readRights()
    {
        $this->rights = array();

        $result = $this->readUserRights($this->permUserId);
        if ($result === false) {
            return false;
        }

        $this->rights = $result;
        return $result;
    }

    /**
     *
     *
     * @access public
     * @param int $permUserId
     * @return mixed array or false on failure
     */
    function readUserRights($permUserId)
    {
        $this->userRights = array();

        $result = $this->_storage->readUserRights($permUserId);
        if ($result === false) {
            return false;
        }

        $this->userRights = $result;
        return $result;
    }

    /**
     * Checks if the current user has a certain right in a
     * given area.
     * If $this->ondemand and $ondemand is true, the rights will be loaded on
     * the fly.
     *
     * @access  public
     * @param   integer $right_id  Id of the right to check for.
     * @param   boolean $ondemand  allow ondemand reading of rights
     * @return  integer Level of the right.
     */
    function checkRight($right_id)
    {
        if (is_array($this->rights)) {
            // check if the user is above areaadmin
            if (!$right_id || $this->userType > LIVEUSER_AREAADMIN_TYPE_ID) {
                return LIVEUSER_MAX_LEVEL;
            } else {
                // If he does, look for the right in question.
                if (in_array($right_id, array_keys($this->rights))) {
                    // We know the user has the right so the right level will be returned.
                    return $this->rights[$right_id];
                }
            }
        }
        return false;
    } // end func checkRight

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
    function checkGroup($group_id, $ondemand = true)
    {
        if (is_array($this->groupIds)) {
            return in_array($group_id, $this->groupIds);
        } elseif ($this->ondemand && $ondemand) {
            $this->readGroups();
            return $this->checkGroup($group_id, false);
        }
        return false;
    } // end func checkGroup

    /**
     * Function returns the inquired value if it exists in the class.
     *
     * @param  string   Name of the property to be returned.
     * @return mixed    null, a value or an array.
     */
    function getProperty($what)
    {
        $that = null;
        if (isset($this->$what)) {
            $that = $this->$what;
        }
        return $that;
    }

    /**
     * store all properties in an array
     *
     * @access  public
     * @return  array containing the property values
     */
    function freeze($sessionName)
    {
        $propertyValues = array(
            'permUserId'  => $this->permUserId,
            'rights'      => $this->rights,
            'userRights'  => $this->userRights,
            'groupRights' => $this->groupRights,
            'userType'    => $this->userType,
            'groupIds'    => $this->groupIds,
        );
        return $this->_storage->freeze($sessionName, $propertyValues);
    } // end func freeze

    /**
     * Reinitializes properties
     *
     * @access  public
     * @param   array  $propertyValues
     */
    function unfreeze($sessionName)
    {
        $propertyValues = $this->_storage->unfreeze($sessionName);
        if ($propertyValues) {
            foreach ($propertyValues as $key => $value) {
                $this->{$key} = $value;
            }
        }
        return $this->init();
    } // end func unfreeze

    /**
     * properly disconnect from resources
     *
     * @access  public
     */
    function disconnect()
    {
        $this->_storage->disconnect();
    }
}
?>