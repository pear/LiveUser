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
 * @package  LiveUser
 * @author  Lukas Smith <smith@backendmedia.com>
 * @copyright 2002-2005 Markus Wolff
 * @license http://www.gnu.org/licenses/lgpl.txt
 * @version CVS: $Id$
 * @link http://pear.php.net/LiveUser
 */

require_once 'LiveUser.php';
require_once 'MDB2.php';
MDB2::loadFile('Tools/Manager');

/*
$dsn = 'mysql://root:@localhost/liveuser_test_installer';

$conf = array(
    'authContainers' => array(
        array(
            'type'          => 'DB',
            'dsn'           => $dsn,
            'authTable'     => 'liveuser_users',
            'authTableCols' => array(
                'required'  => array(
                    'auth_user_id' => array('name' => 'authUserId', 'type' => 'text'),
                    'handle'       => array('name' => 'handle',     'type' => 'text'),
                    'passwd'       => array('name' => 'passwd',     'type' => 'text'),
                ),
                'optional' => array(
                    'lastlogin'    => array('name' => 'lastLogin',  'type' => 'timestamp'),
                    'is_active'    => array('name' => 'isActive',   'type' => 'boolean')
                ),
            ),
        ),
    ),
    'permContainer'  => array(
        'type'  => 'Complex',
        'storage' => array('DB' => array('dsn' => $dsn, 'prefix'     => 'liveuser_')),
    )
);

$installer =& new LiveUser_Misc_Schema_Install();
$result = $installer->installAuthSchema($conf['authContainers'][0], 'auth_mdb_schema.xml', true);
var_dump($result);
$result = $installer->installPermSchema($conf['permContainer']['storage']['DB'], 'perm_mdb_schema.xml', false);
var_dump($result);
*/

class LiveUser_Misc_Schema_Install
{
    function installAuthSchema($config, $file, $create = true)
    {
        if (isset($config['connection'])) {
            $dsn = $config['connection']->dsn;
            if (!isset($dsn['database'])) {
                $dsn['database'] = $config['connection']->database_name;
            }
        } else {
            $dsn = MDB2::parseDSN($config['dsn']);
        }

        $variables = array('database' => $dsn['database']);

        $variables['user_table_name'] = isset($config['authTable'])
            ? $config['authTable'] : 'liveuser_users';
        $variables['auth_user_id_name'] = isset($config['authTableCols']['required']['auth_user_id']['name'])
            ? $config['authTableCols']['required']['auth_user_id']['name'] : 'auth_user_id';
        $variables['handle_name'] = isset($config['authTableCols']['required']['handle']['name'])
            ? $config['authTableCols']['required']['handle']['name'] : 'handle';
        $variables['passwd_name'] = isset($config['authTableCols']['required']['passwd']['name'])
            ? $config['authTableCols']['required']['passwd']['name'] : 'passwd';
        $variables['lastlogin_name'] = isset($config['authTableCols']['optional']['lastlogin']['name'])
            ? $config['authTableCols']['optional']['lastlogin']['name'] : 'lastlogin';
        $variables['is_active_name'] = isset($config['authTableCols']['optional']['is_active']['name'])
            ? $config['authTableCols']['optional']['is_active']['name'] : 'is_active';
        $variables['owner_user_id_name'] = isset($config['authTableCols']['custom']['owner_user_id']['name'])
            ? $config['authTableCols']['custom']['owner_user_id']['name'] : 'owner_user_id';
        $variables['owner_group_id_name'] = isset($config['authTableCols']['custom']['owner_group_id']['name'])
            ? $config['authTableCols']['custom']['owner_group_id']['name'] : 'owner_group_id';

        return $this->installSchema($dsn, $file, $variables, $create);
    }

    function installPermSchema($config, $file, $create = true)
    {
        if (isset($config['connection'])) {
            $dsn = $config['connection']->dsn;
            if (!isset($dsn['database'])) {
                $dsn['database'] = $config['connection']->database_name;
            }
        } else {
            $dsn = MDB2::parseDSN($config['dsn']);
        }

        $variables = array(
            'database' => $dsn['database'],
            'table_prefix' => $config['prefix'],
            'right_max_level' => LIVEUSER_MAX_LEVEL,
        );

        return $this->installSchema($dsn, $file, $variables, $create);
    }

    function installSchema($dsn, $file, $variables, $create = true)
    {
        $manager =& new MDB2_Tools_Manager;

        unset($dsn['database']);

        $options = array(
            'debug' => true,
            'log_line_break' => '<br>',
            'portability' => (MDB2_PORTABILITY_ALL ^ MDB2_PORTABILITY_EMPTY_TO_NULL),
        );

        $err = $manager->connect($dsn, $options);
        if (MDB2::isError($err)) {
            return $err->getMessage().' - '.$err->getUserinfo();
        }

        $variables['create'] = (int)$create;
        $result = $manager->updateDatabase($file, 'old_'.$file, $variables);
        echo('Debug messages<br>');
        echo($manager->db->debugOutput().'<br>');

        $manager->disconnect();
        return $result;
    }
}

?>
