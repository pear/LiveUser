<?php
require_once 'conf.php';
require_once 'LiveUser/Admin/Perm/Container/DB_Medium.php';
require_once 'LiveUser/Admin/Auth/Container/DB.php';

$lu_dsn = array('dsn' => $dsn);

$objRightsAdminAuth = new
    LiveUser_Admin_Auth_Container_DB(
        $lu_dsn, $conf['authContainers'][0]
    );

$objRightsAdminPerm = new
    LiveUser_Admin_Perm_Container_DB_Medium($lu_dsn, $conf);

if (!$objRightsAdminPerm->init_ok) {
    die('impossible to initialize' . $objRightsAdminPerm->getMessage());
}

$objRightsAdminPerm->setCurrentLanguage('FR');

// Add a user to the database
// LiveUser design allowing for several containers
// the user must be added to both containers
$user_auth_id = $objRightsAdminAuth->addUser('johndoe', 'dummypass', true);

if (DB::isError($user_auth_id)) {
    $user_auth_id->getMessage();
    //exit;
}

$user_perm_id = $objRightsAdminPerm->addUser($user_auth_id, key($conf['authContainers']));

echo '$user_id created ' . $user_auth_id . "\n";

// create application and areas
$app_id = $objRightsAdminPerm->addApplication('LIVEUSER', 'website');
$area_id = $objRightsAdminPerm->addArea($app_id, 'ONLY_AREA', 'the one and only area');


// Then he adds three rights
$right_1 = $objRightsAdminPerm->addright($area_id, 'MODIFYNEWS',   'read something');
$right_2 = $objRightsAdminPerm->addright($area_id, 'EDITNEWS',  'write something');

echo 'Created two rights with id ' . $right_1 . ' and ' . $right_2 . "\n";

// Grant the user rights
$objRightsAdminPerm->grantUserRight($user_perm_id, $right_1);
$objRightsAdminPerm->grantUserRight($user_perm_id, $right_2);

$cols = array(
    'name',
    'email'
    );

$filters = array(
    array('email' => array('op' => '=', 'value' => 'fleh@example.com', 'cond' => ''))
);

$userInfo = $objRightsAdminAuth->getUsers($filters, $cols);
print_r($userInfo);
?>
