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
 * PEAR_Auth container for Authentication
 *
 * @package  LiveUser
 * @category authentication
 */

/**
 * Require parent class definition and PEAR::Auth class.
 */
require_once 'LiveUser/Auth/Common.php';
require_once 'Auth/Auth.php';

/**
 * ==================== !!! WARNING !!! ========================================
 *
 *      THIS CONTAINER IS UNDER HEAVY DEVELOPMENT. IT'S STILL IN EXPERIMENTAL
 *      STAGE. USE IT AT YOUR OWN RISK.
 *
 * =============================================================================
 * This is a PEAR::Auth backend driver for the LiveUser class.
 * The general options to setup the PEAR::Auth class can be passed to the constructor.
 * To choose the right auth container, you have to add the 'pearAuthContainer' var to
 * the options array.
 *
 * Requirements:
 * - File "LiveUser.php" (contains the parent class "LiveUser")
 * - PEAR::Auth must be installed in your PEAR directory
 * - Array of setup options must be passed to the constructor.
 *
 * @author  Bjoern Kraus <krausbn@php.net>
 * @version $Id$
 * @package LiveUser
 * @category authentication
 */
class LiveUser_Auth_PEARAuth extends LiveUser_Auth_Common
{
    /**
     * Contains the PEAR::Auth object.
     *
     * @access private
     * @var    Auth
     */
    var $pearAuth = null;

    /**
     * Class constructor.
     *
     * @access protected
     * @param  array     configuration array
     * @return void
     */
    function LiveUser_Auth_PEARAuth(&$connectOptions, $containerName)
    {
        require_once 'Auth.php';
        $this->LiveUser_Auth_Common($connectOptions, $containerName);
        if (!is_object($this->pearAuth)) {
            $this->pearAuth = &new Auth(
                $connectOptions['pearAuthContainer'],
                $connectOptions['pearAuthOptions'],
                '',
                false
            );
            if (PEAR::isError($this->pearAuth)) {
                $this->_stack->push(LIVEUSER_ERROR_INIT_ERROR, 'error', array('container' => 'could not connect: '.$this->pearAuth->getMessage()));
            } else {
                $this->init_ok = true;
            }
        }
    }

    /**
     * Starts and verifies the PEAR::Auth login process
     *
     * @access private
     * @return boolean  true upon success or false on failure
     */
    function _readUserData()
    {
        $this->pearAuth->start();
        $this->pearAuth->login();

        $success = false;

        // If a user was found, read data into class variables and set
        // return value to true
        if ($this->pearAuth->getAuth()) {
            $this->handle       = $this->pearAuth->getUsername();
            $this->passwd       = $this->encryptPW($this->pearAuth->password);
            $this->isActive     = true;
            $this->authUserId   = $this->pearAuth->getUsername();
            $this->lastLogin    = '';

            $success = true;
        }
        return $success;
    }

    /**
     * not yet implemented
     *
     * @return mixed   true or false if the user does not exist
     */
    function userExists()
    {
        return true;
    }

}
?>