<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * A framework for authentication and authorization in PHP applications
 *
 * LiveUser is an authentication/permission framework designed
 * to be flexible and easily extendable.
 *
 * Since it is impossible to have a
 * "one size fits all" it takes a container
 * approach which should enable it to
 * be versatile enough to meet most needs.
 *
 * PHP version 4 and 5 
 *
 * LICENSE: This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public 
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston,
 * MA  02111-1307  USA 
 *
 *
 * @category authentication
 * @package  LiveUser_Admin
 * @author  Markus Wolff <wolff@21st.de>
 * @author Helgi Þormar Þorbjörnsson <dufuz@php.net>
 * @author  Lukas Smith <smith@backendmedia.com>
 * @author Arnaud Limbourg <arnaud@php.net>
 * @author   Pierre-Alain Joye  <pajoye@php.net>
 * @author  Bjoern Kraus <krausbn@php.net>
 * @copyright 2002-2005 Markus Wolff
 * @license http://www.gnu.org/licenses/lgpl.txt
 * @version CVS: $Id$
 * @link http://pear.php.net/LiveUser
 */

/**
 * Base class for permission handling
 *
 * This class provides a set of functions for implementing a user
 * permission management system on live websites. All authorisation
 * backends/containers must be extensions of this base class.
 *
 * @category authentication
 * @package  LiveUser
 * @author  Markus Wolff <wolff@21st.de>
 * @author  Bjoern Kraus <krausbn@php.net>
 * @copyright 2002-2005 Markus Wolff
 * @license http://www.gnu.org/licenses/lgpl.txt
 * @version Release: @package_version@
 * @link http://pear.php.net/LiveUser
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
     * Storage Container
     *
     * @var object
     */
    var $_storage = null;

    /**
     * Class constructor. Feel free to override in backend subclasses.
     */
    function LiveUser_Perm_Simple()
    {
        $this->_stack = &PEAR_ErrorStack::singleton('LiveUser');
    }

    /**
     * Load the storage container
     *
     * @access  public
     * @param  mixed         Name of array containing the configuration.
     * @return  boolean true on success or false on failure
     */
    function init(&$conf)
    {
        if (!isset($conf['storage'])) {
            $this->_stack->push(LIVEUSER_ADMIN_ERROR, 'exception',
                array('msg' => 'Missing storage configuration array'));
            return false;
        }

        if (is_array($conf)) {
            $keys = array_keys($conf);
            foreach ($keys as $key) {
                if (isset($this->$key)) {
                    $this->$key =& $conf[$key];
                }
            }
        }

        $this->_storage = LiveUser::storageFactory($conf['storage']);
        if ($this->_storage === false) {
            $this->_stack->push(LIVEUSER_ADMIN_ERROR, 'exception',
                array('msg' => 'Could not instanciate storage container'));
            return false;
        }

        return true;
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
    function mapUser($authUserId = null, $containerName = null)
    {
        $result = $this->_storage->mapUser($authUserId, $containerName);
        if ($result === false) {
            return false;
        }

        if (is_null($result)) {
            return false;
        }

        $this->permUserId = $result['perm_user_id'];
        $this->userType   = $result['perm_type'];

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
        return $this->rights;
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
        return $this->userRights;
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
        // check if the user is above areaadmin
        if (!$right_id || $this->userType > LIVEUSER_AREAADMIN_TYPE_ID) {
            return LIVEUSER_MAX_LEVEL;
        // If he does, look for the right in question.
        } elseif (is_array($this->rights) && isset($this->rights[$right_id])) {
            // We know the user has the right so the right level will be returned.
            return $this->rights[$right_id];
        }
        return false;
    } // end func checkRight

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
        return true;
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