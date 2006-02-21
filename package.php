<?php
/**
 * Script to generate package.xml file
 *
 * Taken from PEAR::Log, thanks Jon ;)
 *
 * $Id$
 */
require_once 'PEAR/PackageFileManager.php';
require_once 'Console/Getopt.php';

$version = '0.16.9';

$notes = <<<EOT
This releases fixes a minor security issue that is limited to the optional
remember me feature. This issue was report to us by GulfTech Security Research.

The issue would allow an attacker to determine the existance of files inside the
file system, as well as being able to delete files:
- if the relativ path is shorter than 32 characters (including a null
byte)
- if null bytes are handled inside the "_COOKIE" superglobal, for example
through usage of magic_quotes_gpc, the issue becomes essentially limited to
files ending with ".lu".

All installations using the remember me feature are strongly urged to update.
This release also changes some other aspects including a BC break so developers
can optionally patch their current installations from the changes in the
following commit:
http://cvs.php.net/viewcvs.cgi/pear/LiveUser/LiveUser.php?r1=1.148&r2=1.149&diff_format=u

- fixed major bug in PEARAuth container: auth_user_id is not an optional property
- added passwordEncryptionMode and secret to phpdoc comment
- made cryptRC4() method public to match usage in auth common in the client and admin api
- fixed handling of the secret user defineable property (bug #6551)
- added support for user_group_ids (bug #6517)
- allow grouprights and groupusers table to join eachother
- updateProperty doesn't update the session (bug #6612)
- renamed "connection" config option to "dbc" *BC BREAK*
- cleaned up and unified init() in the storage classes
- added example for dumping SQL to a file to installer
- add support for force_seq to installer
- removed allowDuplicateHandles and allowEmptyPasswords options, they are now
  handled through the table definition in the given Globals.php (overwriteable
  via the config array) *BC BREAK*
- initial untested support for PDO in the installer
- added examples for setting length and defaults to installer
- use overwrite when unlink is enabled in the installer
- reworked handling of merging user with group rights *BC BREAK*
When using the Medium or Complex container a user may gain rights through direct
assignment or through membership in a group that has rights assigned. The user
and group rights are merged with the following logic:
* if the right is only assigned to a member group but not the user the right is
  available to the user at the level at which the group has the right
* if the right is only assigned to the user at a level greater than zero but not
  to a member group the right is available to the user at the level at which
  user has the right
* if the right is only assigned to the user at a level equal to zero but not
  to a member group the right is available to the user at the level at which
  user has the right
* if the right is only assigned to the user at a level lower than zero but not
  to a member group then the right is unavailable to the user
* if the is assigned to a member group and the user and the level at which the
  user has the right is greater than zero, then the right is available to the
  user at higher level of the two
* if the is assigned to a member group and the user and the level at which the
  user has the right is equal to zero, then the right is unavailable to the user
* if the is assigned to a member group and the user and the level at which the
  user has the right is lower than zero, then the right is available to the
  user at the minimum of the group assigned level and the addition of the
  negativ user level and the maximum level
Example:
The user as the following right_id => level pairs
array
  1 => 3
  2 => -2
  3 => 0
  5 => -1

The groups he is a member of have the following right_id => level pairs
array
  1 => 1
  2 => 3
  3 => 3
  4 => 2

The final right_id => level pairs are as follows
array
  1 => 3 // user has a higher level (3) than the group level (1)
  2 => 1 // 3 - 2 means a maximum possible level of 1
  4 => 2 // only group has the right at level 2
  5 => 2 // only user has the right at level 3 - 1 = 2

EOT;

$description = <<<EOT
  LiveUser is a set of classes for dealing with user authentication
  and permission management. Basically, there are three main elements that
  make up this package:

  * The LiveUser class
  * The Auth containers
  * The Perm containers

  The LiveUser class takes care of the login process and can be configured
  to use a certain permission container and one or more different auth containers.
  That means, you can have your users' data scattered amongst many data containers
  and have the LiveUser class try each defined container until the user is found.
  For example, you can have all website users who can apply for a new account online
  on the webserver's local database. Also, you want to enable all your company's
  employees to login to the site without the need to create new accounts for all of
  them. To achieve that, a second container can be defined to be used by the LiveUser class.

  You can also define a permission container of your choice that will manage the rights for
  each user. Depending on the container, you can implement any kind of permission schemes
  for your application while having one consistent API.

  Using different permission and auth containers, it's easily possible to integrate
  newly written applications with older ones that have their own ways of storing permissions
  and user data. Just make a new container type and you're ready to go!

  Currently available are containers using:
  PEAR::DB, PEAR::MDB, PEAR::MDB2, PECL::PDO, PEAR::XML_Tree, PEAR::Auth, Session.
EOT;

$package = new PEAR_PackageFileManager();

$result = $package->setOptions(array(
    'package'           => 'LiveUser',
    'summary'           => 'User authentication and permission management framework',
    'description'       => $description,
    'version'           => $version,
    'state'             => 'beta',
    'license'           => 'LGPL',
    'filelistgenerator' => 'cvs',
    'ignore'            => array('package.php', 'package.xml', 'TODO', 'DefineGenerator'),
    'notes'             => $notes,
    'changelogoldtonew' => false,
    'simpleoutput'      => true,
    'baseinstalldir'    => '/LiveUser',
    'packagedirectory'  => './',
    'installexceptions' => array(
        'LiveUser.php'            => '/',
    ),
    'installas'         => array(
        'sql/Auth_XML.xml'           => 'misc/Auth_XML.xml',
        'sql/Perm_XML.xml'           => 'misc/Perm_XML.xml',
        'sql/README'                 => 'misc/schema/README',
        'sql/install.php'            => 'misc/schema/install.php',
    ),
    'exceptions'         => array(
        'lgpl.txt' => 'doc'
    ),
    'dir_roles'         => array('sql'               => 'data',
                                 'docs'              => 'doc',
                                 'scripts'           => 'script')
));

if (PEAR::isError($result)) {
    echo $result->getMessage();
    exit();
}

$package->addMaintainer('mw21st',  'lead',      'Markus Wolff',      'mw21st@php.net');
$package->addMaintainer('arnaud',  'lead',      'Arnaud Limbourg',   'arnaud@php.net');
$package->addMaintainer('lsmith',  'lead',      'Lukas Kahwe Smith', 'smith@pooteeweet.org');
$package->addMaintainer('krausbn', 'developer', 'Bjoern Kraus',      'krausbn@php.net');
$package->addMaintainer('dufuz',   'lead',      'Helgi Þormar',     'dufuz@php.net');

$package->addDependency('php',              '4.2.0',    'ge',  'php', false);
$package->addDependency('PEAR',             '1.3.3',    'ge',  'pkg', false);
$package->addDependency('Event_Dispatcher', false,      'has', 'pkg', false);
$package->addDependency('Log',              '1.7.0',    'ge',  'pkg', true);
$package->addDependency('DB',               '1.6.0',    'ge',  'pkg', true);
$package->addDependency('MDB',              '1.1.4',    'ge',  'pkg', true);
$package->addDependency('MDB2',             '2.0.0RC1', 'ge',  'pkg', true);
$package->addDependency('MDB2_Schema',      false,        'has', 'pkg', true);
$package->addDependency('XML_Tree',         false,        'has', 'pkg', true);
$package->addDependency('Crypt_RC4',        false,        'has', 'pkg', true);

if (array_key_exists('make', $_GET) || (isset($_SERVER['argv'][1]) && $_SERVER['argv'][1] == 'make')) {
    $result = $package->writePackageFile();
} else {
    $result = $package->debugPackageFile();
}

if (PEAR::isError($result)) {
    echo $result->getMessage();
    exit();
}

echo "package.xml generated successfully!\n";
