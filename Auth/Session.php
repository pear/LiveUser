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
 * Session based container for Authentication
 *
 * @package  LiveUser
 * @category authentication
 */

require_once 'LiveUser/Auth/Common.php';

/**
 * This is a backend driver for a simple session based anonymous LiveUser class.
 *
 * Requirements:
 * - File "LiveUser.php" (contains the parent class "LiveUser")
 *
 * @author  Lukas Smith <smith@backendmedia.com>
 * @version $Id$
 * @package LiveUser
 * @category authentication
 */
class LiveUser_Auth_Session extends LiveUser_Auth_Common
{
    /**
     * name of the key containing the Session phrase inside the auth session array
     *
     * @access public
     * @var    string
     */
    var $sessionKey = 'password';

    function init(&$conf, $containerName)
    {
        parent::init($conf, $containerName);

        return true;
    }

    /**
     * Writes current values for user back to the database.
     * This method does nothing in the base class and is supposed to
     * be overridden in subclasses according to the supported backend.
     *
     * @access private
     * @return boolean true on success or false on failure
     */
    function _updateUserData()
    {
        $this->lastLogin    = $this->currentLogin;
        return true;
    }

    /**
     * Reads auth_user_id, passwd, is_active flag
     * lastlogin timestamp from the database
     * If only $handle is given, it will read the data
     * from the first user with that handle and return
     * true on success.
     * If $handle and $passwd are given, it will try to
     * find the first user with both handle and password
     * matching and return true on success (this allows
     * multiple users having the same handle but different
     * passwords - yep, some people want this).
     * If no match is found, false is being returned.
     *
     * @access private
     * @param  string   user handle
     * @param  boolean  user password
     * @return boolean true on success or false on failure
     */
    function _readUserData($handle, $passwd = '')
    {
        if (isset($this->authTableCols['required']['passwd'])
            && $this->authTableCols['required']['passwd']
        ) {
            if (!isset($_SESSION[$this->authTableCols['required']['passwd']['name']]) ||
                $_SESSION[$this->authTableCols['required']['passwd']['name']] !== $passwd
            ) {
                return false;
            }
        }

        $this->handle       = $handle;
        $this->passwd       = $passwd;
        $this->isActive     = true;
        $this->lastLogin    = time();

        return true;
    }
}
?>