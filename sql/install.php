<?php

// $file is the name of the schema to be inserted into the database
// $config

class LiveUser_Misc_SQL_Install
{
    function installAuthSchema($config, $file)
    {
        if (isset($config['connection'])) {
            $dsn = $config['connection']->dsn;
        } else {
            $dsn = $config['dsn'];
        }
 
        $variables = array('user_table_name' => $config['authTable']);
        $variables['auth_user_id_name'] = isset($config['required']['auth_user_id']['name'])
            ? $config['required']['auth_user_id']['name'] : 'auth_user_id';
        $variables['handle_name'] = isset($config['required']['handle']['name'])
            ? $config['required']['handle']['name'] : 'handle';
        $variables['passwd_name'] = isset($config['required']['passwd']['name'])
            ? $config['required']['passwd']['name'] : 'passwd';
        $variables['lastlogin_name'] = isset($config['required']['lastlogin']['name'])
            ? $config['required']['lastlogin']['name'] : 'lastlogin';
        $variables['owner_user_id_name'] = isset($config['required']['owner_user_id']['name'])
            ? $config['required']['owner_user_id']['name'] : 'owner_user_id';
        $variables['owner_group_id_name'] = isset($config['required']['owner_group_id']['name'])
            ? $config['required']['owner_group_id']['name'] : 'owner_group_id';
        $variables['is_active_name'] = isset($config['required']['is_active']['name'])
            ? $config['required']['is_active']['name'] : 'is_active';
 
        return $this->installSchema($dsn, $file, $variables);
    }

    function installPermSchema($config, $file)
    {
        if (isset($config['connection'])) {
            $dsn = $config['connection']->dsn;
        } else {
            $dsn = $config['dsn'];
        }

        $variables = array(
            'table_prefix' => $config['prefix'],
            'right_max_level' => LIVEUSER_MAX_LEVEL,
        );

        return $this->installSchema($dsn, $file, $variables);
    }

    function installSchema($dsn, $file, $variables)
    {
        require_once 'MDB2.php';
        MDB2::loadFile('Tools/Manager');

        $manager =& new MDB2_Tools_Manager;
        $err = $manager->connect($dsn);
        if (MDB2::isError($err)) {
            return $err->getMessage();
        }

        $result = $manager->updateDatabase($file, 'old_'.$file, $variables);
        $manager->disconnect();
        return $result;
    }
}

?>
