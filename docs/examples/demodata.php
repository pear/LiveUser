<?php
/**
 * This script will populate the database with the
 * necessary data to run the example.
 *
 *
 * Syntax:
 * DefineGenerator [options]
 * ...where [options] can be:
 * -h --help : Shows this list of options
 *
 * -d --dsn (required): Defines the PEAR::DB DSN to connect to the database.
 * Example: --dsn=mysql://user:passwd@hostname/databasename
 * or -d "mysql://user:passwd@hostname/databasename"
 *
 * -f --file (required): input file containing the structure and
 *                       data in MDB2_Schema format.
 * Example: --file=/path/to/output/file.xml
 *
 * Example usage: php demodata.php -d mysql://root:@localhost/lu_test -f 
 * example5/demodata.xml
 *
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
 * @category  authentication
 * @package   LiveUser
 * @author    Lukas Smith <smith@backendmedia.com>
 * @author    Arnaud Limbourg <arnaud@limbourg.com>
 * @copyright 2002-2005 Markus Wolff
 * @license   http://www.gnu.org/licenses/lgpl.txt
 * @version   CVS: $Id$
 * @link      http://pear.php.net/LiveUser
 */

require_once 'MDB2/Schema.php';
require_once 'Console/Getopt.php';
require_once 'System.php';

$argv = Console_Getopt::readPHPArgv();

$shortoptions = "h?d:f:";

$longoptions = array('file=', 'dsn=');

$dsn = $file = '';

$con = new Console_Getopt;
$args = $con->readPHPArgv();
array_shift($args);
$options = $con->getopt($args, $shortoptions, $longoptions);

if (PEAR::isError($options)) {
    printHelp($options);
}

$options = $options[0];
foreach ($options as $opt) {
    switch ($opt[0]) {
        case 'd':
        case '--dsn':
        $dsn = $opt[1];
        break;
        case 'f':
        case '--file':
        $file = $opt[1];
        break;
        case 'h':
        case '--help':
        printHelp();
        break;
}
}

/******************************************************************
Begin sanity checks on arguments
******************************************************************/
if ($dsn == '' && $file == '') {
    printHelp();
}

if (!file_exists($file)) {
    print "The file $file does not exist\n";
    exit();
}
/******************************************************************
End sanity checks on arguments
******************************************************************/

$lu_example_data = System::tmpdir() . DIRECTORY_SEPARATOR . '_lu_example_data.xml';

$fread  = @fopen($file, 'rb');
$fwrite = @fopen($lu_example_data, 'wb');

if ($fread === false || $fwrite === false) {
    print "I couldn't not open the file\n";
    $open_error = ($fread === false) ? "The source file $file cannot be opened" : "The destination file $lu_example_data cannot be created, are the correct permissions set ?";
    print "$open_error\n";
    exit();
}

register_shutdown_function('liveuser_demo_data_cleanup');

$dsninfo = MDB2::parseDSN($dsn);

while (!feof($fread)) {
    $buffer = fgets($fread, 4096);
    if (strpos($buffer, '%database%') !== false) {
        $buffer = str_replace('%database%', $dsninfo['database'], $buffer);
    }
    fwrite($fwrite, $buffer);
}

fclose($fread);
fclose($fwrite);

print "\n";

$manager =& new MDB2_Schema;

$options = array(
    'debug'          => true,
    'log_line_break' => '<br>',
    'portability'    => (MDB2_PORTABILITY_ALL ^ MDB2_PORTABILITY_EMPTY_TO_NULL),
);

$err = $manager->connect($dsn, $options);

if (PEAR::isError($err)) {
   print "I could not connect to the database\n";
   print "  " . $err->getMessage()  . "\n";
   print "  " . $err->getUserInfo() . "\n";
   exit();
}

$res = $manager->updateDatabase($lu_example_data);

if (PEAR::isError($res)) {
    print "I could not populate the database, see error below\n";
    print "  " . $res->getMessage()  . "\n";
    print "  " . $res->getUserInfo() . "\n";
} else {
    print "Database populated successfully\n";
}

/**
 * printHelp()
 *
 * @return void
 * @desc Prints out a list of commandline options
 */
function printHelp()
{
echo ('
Syntax:
DefineGenerator [options]

...where [options] can be:
-h --help : Shows this list of options

-d --dsn (required) : Defines the PEAR::DB DSN to connect to the database.
Example: --dsn=mysql://user:passwd@hostname/databasename

-f --file (required) : input file containing the structure and
data in MDB2_Schema format. Example: --file=/path/to/output/file.xml

Example usage: Make sure the database exists beforehand

php demodata.php -d mysql://root:@localhost/lu_test -f example5/demodata.xml

');
exit;
}

function liveuser_demo_data_cleanup() {
    global $lu_example_data;
    if (file_exists($lu_example_data)) {
        System::rm($lu_example_data);
    }
}
?>