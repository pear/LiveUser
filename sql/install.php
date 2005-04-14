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
require_once 'MDB2/Schema.php';

$dsn = 'mysql://root:@localhost/liveuser_test_installer';

$conf = array(
    'authContainers' => array(
        array(
            'type'          => 'MDB2',
            'dsn'           => $dsn,
            'authTable'     => 'liveuser_users',
            'authTableCols' => array(
                'required'  => array(
                    'auth_user_id' => array('name' => 'authUserId', 'type' => 'text'),
                    'handle'       => array('name' => 'handle',     'type' => 'text'),
                    'passwd'       => array('name' => 'passwd',     'type' => 'text'),
                ),
                'optional' => array(
                    'owner_user_id'  => array('name' => 'owner_user_id',    'type' => 'integer'),
                    'owner_group_id' => array('name' => 'owner_group_id',   'type' => 'integer'),
                    'lastlogin'      => array('name' => 'lastLogin',        'type' => 'timestamp'),
                    'is_active'      => array('name' => 'isActive',         'type' => 'boolean')
                ),
            ),
        ),
    ),
    'permContainer'  => array(
        'type'  => 'Complex',
        'storage' => array('MDB2' => array('dsn' => $dsn, 'prefix' => 'liveuser_')),
    )
);

$installer =& new LiveUser_Misc_Schema_Install();

$options = array(
    'debug' => true,
    'log_line_break' => '<br>',
    'portability' => (MDB2_PORTABILITY_ALL ^ MDB2_PORTABILITY_EMPTY_TO_NULL),
);

$result = $installer->installAuthSchema($conf['authContainers'][0], 'auth_mdb_schema.xml', true, $options);
var_dump($result);
$result = $installer->installPermSchema($conf['permContainer']['storage'], 'perm_mdb_schema.xml', false, $options);
var_dump($result);

class LiveUser_Misc_Schema_Install
{
    function installAuthSchema($config, $file, $create = true, $options = array())
    {
        $auth =& LiveUser::authFactory($config, 'foo');
        if (!$auth) {
            return false;
        }

        $dsn = $auth->dbc->dsn;
        if (is_a($auth->dbc, 'DB_Common')) {
            $options['sequence_col_name'] = 'id';
        } else {
            $dsn['database'] = $auth->dbc->database_name;
        }

        $variables = array('database' => $dsn['database']);

        $variables['user_table_name'] = $auth->authTable;
        $variables['auth_user_id_name'] = $auth->authTableCols['required']['auth_user_id']['name'];
        $variables['handle_name'] = $auth->authTableCols['required']['handle']['name'];
        $variables['passwd_name'] = $auth->authTableCols['required']['passwd']['name'];
        $variables['lastlogin_name'] = $auth->authTableCols['optional']['lastlogin']['name'];
        $variables['is_active_name'] = $auth->authTableCols['optional']['is_active']['name'];
        $variables['owner_user_id_name'] = $auth->authTableCols['optional']['owner_user_id']['name'];
        $variables['owner_group_id_name'] = $auth->authTableCols['optional']['owner_group_id']['name'];

        return $this->installSchema($dsn, $file, $variables, $create, $options);
    }

    function installPermSchema($config, $file, $create = true, $options = array())
    {
        $perm =& LiveUser::storageFactory($config);
        if (!$perm) {
            return false;
        }

        $dsn = $perm->dbc->dsn;
        if (is_a($perm->dbc, 'DB_Common')) {
            $options['sequence_col_name'] = 'id';
        } else {
            $dsn['database'] = $perm->dbc->database_name;
        }

        $variables = array(
            'database' => $dsn['database'],
            'table_prefix' => $perm->prefix,
            'right_max_level' => LIVEUSER_MAX_LEVEL,
        );

        return $this->installSchema($dsn, $file, $variables, $create, $options);
    }

    function installSchema($dsn, $file, $variables, $create = true, $options = array())
    {
        $manager =& new MDB2_Schema;

        unset($dsn['database']);

        $err = $manager->connect($dsn, $options);
        if (MDB2::isError($err)) {
            return $err;
        }

        $variables['create'] = (int)$create;
        $result = $manager->updateDatabase($file, $file.'.old', $variables);

        $debug = $manager->getOption('debug');
        if ($debug && !PEAR::isError($debug)) {
            echo('Debug messages<br>');
            echo($manager->db->debugOutput().'<br>');
        }
        $manager->disconnect();
        return $result;
    }
}

?>
