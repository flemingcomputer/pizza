<?php
// Copyright 2016 Fleming Computer.
// All Rights Reserved.
?>
<?php
header('Content-Type: text/html; charset=utf-8');
?>
<?php
function checkPizzaConfig()
{
    if (!is_file('config.php')) return;
    // Check key config variables for sane values.
    require('config.php');
    $config = get_defined_vars();
    if (($config['adminEmail'] ?? '') === '') return;
    if (($config['dbHost'] ?? '') === '') return;
    if (($config['dbUser'] ?? '') === '') return;
    if (($config['dbPassword'] ?? '') === '') return;
    if (($config['dbDatabase'] ?? '') === '') return;
    if (($config['hidePizzaRoot'] ?? '') === '') return;
    // It appears that config.php has been created, but does the database work?
    $host = $config['dbHost'];
    $user = $config['dbUser'];
    $password = $config['dbPassword'];
    $database = $config['dbDatabase'];
    if (!checkDatabaseCredentials($host, $user, $password, $database))
        exitConfigExistsButDbProblem();
    // config.php connects successfully to database, so just go to Pizza.
    if ($config['hidePizzaRoot'] === true)
        header('Location: /');
    else
        header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/');
    exit();
    // exitConfigExists();
}
checkPizzaConfig();

// Check for PHP 8.
if (preg_match('/(\d+)\./', PHP_VERSION, $matches))
{
    $phpVersion = $matches[1];
    if ($phpVersion < 8) exitPhpVersionError();
}

// Check for MySQLi extension.
if (!extension_loaded('mysqli'))
{
    exitMysqliNotLoaded();
}

// Check MySQL host and version.
$hosts = array(
    'localhost',
    '127.0.0.1'
);
$port = 3306;
$timeout = 2;
$hostOk = false;
$versionOk = false;
foreach ($hosts as $host)
{
    $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
    if (!$connection) continue;
    // Read the first 128 bytes of the MySQL handshake packet.
    $packet = fread($connection, 128);
    fclose($connection);
    if (strlen($packet) > 5)
    {
        // Byte 0-2: Packet length (3 bytes)
        // Byte 3: Packet number (1 byte)
        // Byte 4: Protocol version (1 byte, usually 0x0a for modern MySQL)
        $protocol = ord($packet[4]);
    }
    if ($protocol == 10)
    {
        $hostOk = true;
        // Starting at byte 5 is the null-terminated version string.
        // We cut the string from byte 5 onward and stop at the first null byte (\x00).
        $version = substr($packet, 5);
        $nullByte = strpos($version, "\x00");
        if ($nullByte !== false)
        {
            $version = substr($version, 0, $nullByte);
            if (preg_match('/(\d+)\./', $version, $matches))
            {
                $version = $matches[1];
                if ($version >= 8)
                {
                    $versionOk = true;
                    break;
                }
            }
        }
    }
}
// echo "host: " . htmlspecialchars($host) . "<br>\n";
// echo "version: " . htmlspecialchars($version) . "<br>\n";
// exit();
if (!$hostOk) exitMysqlVersionCantDetect();
if (!$versionOk) exitMysqlVersionWrong();

$hasPizzaUser = isset($_POST['pizzaInstall_user']);
$hasPizzaDatabase = isset($_POST['pizzaInstall_database']);
$hasAdminEmail = isset($_POST['pizzaInstall_adminEmail']);
$hasAdminName = isset($_POST['pizzaInstall_adminName']);
$hasAdminPassword1 = isset($_POST['pizzaInstall_adminPassword1']);
$hasAdminPassword2 = isset($_POST['pizzaInstall_adminPassword2']);
$hasMysqlUser = isset($_POST['pizzaInstall_mysqlUser']);
$hasMysqlPassword = isset($_POST['pizzaInstall_mysqlPassword']);
$pizzaUser = substr(trim(pge('pizzaInstall_user', '')), 0, 17);
$pizzaDatabase = substr(trim(pge('pizzaInstall_database', '')), 0, 65);
$adminEmail = substr(trim(pge('pizzaInstall_adminEmail', '')), 0, 81);
$adminName = substr(trim(pge('pizzaInstall_adminName', '')), 0, 41);
$adminPassword1 = substr(trim(pge('pizzaInstall_adminPassword1', '')), 0, 21);
$adminPassword2 = substr(trim(pge('pizzaInstall_adminPassword2', '')), 0, 21);
$mysqlUser = substr(trim(pge('pizzaInstall_mysqlUser', '')), 0, 17);
$mysqlPassword = substr(trim(pge('pizzaInstall_mysqlPassword', '')), 0, 101);

function checkDatabaseCredentials($host, $user, $password, $database)
{
    try {
        $mysqli = new mysqli($host, $user, $password, $database);
    } catch (Exception $e) {
        return false;
    }
    $mysqli->close();
    return true;
}

function cleanUpOnError()
{
    // // Undo the database and user creation.
    // global $mPizzaDatabase, $mPizzaUser, $host, $mysqlUser, $mysqlPassword;
    // try {
    //     $mysqli = new mysqli($host, $mysqlUser, $mysqlPassword);
    //     $q = "DROP DATABASE IF EXISTS `$mPizzaDatabase`";
    //     $result = $mysqli->query($q);
    //     $q = "DROP USER IF EXISTS '$mPizzaUser'@'$host'";
    //     $result = $mysqli->query($q);
    // } catch (Exception $e) {
    //     return;
    // }
}

function exitConfigExists()
{
    ob_start();
    ?>
    <h1>Pizza Already Installed?</h1>
    <p>
    Pizza appears to have been installed already, as the configuration file “config.php” has been created and the database connects successfully.
    </p>
    <p>
    Have a look at your configuration, and if you’re sure you want to reinstall Pizza, delete “config.php” and the MySQL user/database and <a href="install">try installing Pizza again</a>.
    </p>
    <?php
    renderHtml5(ob_get_clean());
}

function exitConfigExistsButDbProblem()
{
    ob_start();
    ?>
    <h1>Pizza Already Installed?</h1>
    <p>
    Pizza appears to have been installed already, as the configuration file “config.php” has been created, but connection to the database isn't working.
    </p>
    <p>
    Have a look at your configuration, and if you’re sure you want to reinstall Pizza, delete “config.php” and the MySQL user/database and <a href="install">try installing Pizza again</a>.
    </p>
    <?php
    renderHtml5(ob_get_clean());
}

function exitInstalledSuccessfully()
{
    // Strip off query string.
    $path = explode('?', $_SERVER['REQUEST_URI'], 2);
    $uri = $path[0];
    // Remove last part (.../install).
    $parts = explode('/', $uri);
    unset($parts[count($parts) - 1]);
    $startUrl = implode('/', $parts);
    ob_start();
    ?>
    <h1>Pizza Installed Successfully</h1>
    <p>Congratulations, you’ve successfully installed Pizza!</p>
    <p><a href="<?=$startUrl?>/">Click here to start using Pizza</a>!</p>
    <?php
    renderHtml5(ob_get_clean());
}

function exitInstalledSuccessfullyButCantChmod()
{
    // Strip off query string.
    $path = explode('?', $_SERVER['REQUEST_URI'], 2);
    $uri = $path[0];
    // Remove last part (.../install).
    $parts = explode('/', $uri);
    unset($parts[count($parts) - 1]);
    $startUrl = implode('/', $parts);
    ob_start();
    ?>
    <h1>Pizza Installed Successfully, But...</h1>
    <p>
    You’ve successfully installed Pizza, and the configuration file “config.php” was created, but the file could not be changed to read-only and is therefore still world-writable.
    </p>
    <p>
    Until you change the mode of “config.php” to read-only, you will see a warning atop your website. But in the meantime, you can <a href="<?=$startUrl?>/">click here to start using Pizza</a>!
    </p>
    <?php
    renderHtml5(ob_get_clean());
}

function exitInstalledSuccessfullyButCantWrite($adminEmail, $host, $pizzaUser, $pizzaDatabase, $pizzaPassword)
{
    // Strip off query string.
    $path = explode('?', $_SERVER['REQUEST_URI'], 2);
    $uri = $path[0];
    // Remove last part (.../install).
    $parts = explode('/', $uri);
    unset($parts[count($parts) - 1]);
    $startUrl = implode('/', $parts);
    ob_start();
    ?>
<h1>Pizza Installed Successfully, But...</h1>
<p>
You’ve successfully installed Pizza, but the configuration file “config.php” couldn’t be created. This is usually due to protective write permissions on your server.
</p>
<p>
As a result, you won’t be able to access your Pizza installation until you manually create “config.php”. To do this, copy the file “config.php.sample” under your Pizza directory to “config.php”, then edit “config.php” and set the following:
</p>

<pre>
$adminEmail = '<?=$adminEmail?>';
$dbHost = '<?=$host?>';
$dbUser = '<?=$pizzaUser?>';
$dbPassword = '<?=$pizzaPassword?>';
$dbDatabase = '<?=$pizzaDatabase?>';
</pre>

<p>Once that’s done, <a href="<?=$startUrl?>/">click here to start using Pizza</a>!</p>
    <?php
    renderHtml5(ob_get_clean());
}

function exitMysqlConnectError($user)
{
    ob_start();
    ?>
    <h1>Hmm...</h1>
    <p>
    Unable to connect to MySQL server as “<?=myHtmlEntities($user)?>”. <a href="install">Try again</a>?
    </p>
    <?php
    renderHtml5(ob_get_clean());
}

function exitMysqlDatabaseCreationError($pizzaDatabase)
{
    cleanUpOnError();
    ob_start();
    ?>
    <h1>Hmm...</h1>
    <p>
    The MySQL database “<?=myHtmlEntities($pizzaDatabase)?>” could not be created. <a href="install">Try again</a>?
    </p>
    <?php
    renderHtml5(ob_get_clean());
}

function exitMysqlGrantDatabaseError($pizzaUser, $pizzaDatabase)
{
    cleanUpOnError();
    ob_start();
    ?>
    <h1>Hmm...</h1>
    <p>
    Unable to grant MySQL privileges to user “<?=myHtmlEntities($pizzaUser)?>” for database “<?=myHtmlEntities($pizzaDatabase)?>”. <a href="install">Try again</a>?
    </p>
    <?php
    renderHtml5(ob_get_clean());
}

function exitMysqlGrantUsageError($pizzaUser)
{
    cleanUpOnError();
    ob_start();
    ?>
    <h1>Hmm...</h1>
    <p>
    Unable to grant usage to user “<?=myHtmlEntities($pizzaUser)?>”. <a href="install">Try again</a>?
    </p>
    <?php
    renderHtml5(ob_get_clean());
}

function exitMysqlImportError($q)
{
    cleanUpOnError();
    ob_start();
    ?>
    <h1>Hmm...</h1>
    <p>
    Unable to import Pizza primer database.
    </p>
    <h2>Query</h2>
    <?=myHtmlEntities($q)?>
    <p><a href="install">Try again</a>?</p>
    <?php
    renderHtml5(ob_get_clean());
}

function exitMysqliNotLoaded()
{
    ob_start();
    ?>
    <h1>Hmm...</h1>
    <p>
    The MySQLi extension is required but doesn't seem to be loaded on your server.
    Unable to continue with Pizza installation.
    </p>
    <?php
    renderHtml5(ob_get_clean());
}

function exitMysqlInsertAdministratorError($adminEmail, $adminName)
{
    cleanUpOnError();
    ob_start();
    ?>
    <h1>Hmm...</h1>
    <p>
    Unable to insert “<?=myHtmlEntities($adminName)?> &lt;<?=myHtmlEntities($adminEmail)?>&gt;” as the Pizza administrator into your Pizza database. <a href="install">Try again</a>?
    </p>
    <?php
    renderHtml5(ob_get_clean());
}

function exitMysqlSelectDatabaseError($pizzaUser, $pizzaDatabase)
{
    cleanUpOnError();
    ob_start();
    ?>
    <h1>Hmm...</h1>
    <p>
    Unable to select MySQL database “<?=myHtmlEntities($pizzaDatabase)?>” using user “<?=myHtmlEntities($pizzaUser)?>”. <a href="install">Try again</a>?
    </p>
    <?php
    renderHtml5(ob_get_clean());
}

function exitMysqlSetPageOwnershipError($adminEmail, $adminName)
{
    cleanUpOnError();
    ob_start();
    ?>
    <h1>Hmm...</h1>
    <p>
    Unable to give ownership of Pizza’s MySQL database content to “<?=myHtmlEntities($adminName)?> &lt;<?=myHtmlEntities($adminEmail)?>&gt;”. <a href="install">Try again</a>?
    </p>
    <?php
    renderHtml5(ob_get_clean());
}

function exitMysqlUserCreationError($pizzaUser)
{
    cleanUpOnError();
    ob_start();
    ?>
    <h1>Hmm...</h1>
    <p>
    The MySQL user “<?=myHtmlEntities($pizzaUser)?>” could not be created. <a href="install">Try again</a>?
    </p>
    <?php
    renderHtml5(ob_get_clean());
}

function exitMysqlVersionCantDetect()
{
    ob_start();
    ?>
    <h1>Hmm...</h1>
    <p>
    Cannot detect if or which version of MySQL is running (it must be 8 or higher). Unable to continue with Pizza installation.
    </p>
    <?php
    renderHtml5(ob_get_clean());
}

function exitMysqlVersionWrong()
{
    ob_start();
    ?>
    <h1>Hmm...</h1>
    <p>
    MySQL must be version 8 or higher. Unable to continue with Pizza installation.
    </p>
    <?php
    renderHtml5(ob_get_clean());
}

function exitPhpVersionError()
{
    ob_start();
    ?>
    <h1>Hmm...</h1>
    <p>
    PHP must be version 8 or higher. Unable to continue with Pizza installation.
    </p>
    <?php
    renderHtml5(ob_get_clean());
}

function generatePizzaPassword()
{
    $alphabet = "abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789";
    $password = '';
    $alphaLength = strlen($alphabet) - 1;
    for ($i = 0; $i < 16; $i++)
    {
        $n = mt_rand(0, $alphaLength);
        $password .= $alphabet[$n];
    }
    return $password;
}

function generatePizzaUserDatabase()
{
    return 'pizza';
    // Base the name on the host if defined.
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host == '') return 'pizza';
    $host = str_replace(
        array('.com', '.edu', '.gov', '.info', '.net', '.org', '.', '-', '_'),
        '', $host
    );
    return substr("pizza_$host", 0, 16);
}

function myHtmlEntities($s)
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function pge($field, $default)
{
    if (isset($_POST[$field])) return $_POST[$field];
    return $default;
}

function renderHtml5($body)
{
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<style>
/* Commodore 64
    html::before {
        content: "";
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        box-sizing: border-box;
        border-color: #79a7fc;
        border-style: solid;
        border-width: 60px 40px 60px 40px;
        z-index: 9999;
        pointer-events: none;
    }

    body {
        background-color: #3b63d4;
        color: #79a7fc;
        font-family: courier;
        margin: 0;
        padding: 60px;
    }

    input:not([type="submit"]) {
        background-color: #1e336d;
        border-color: #79a7fc;
        border-style: solid;
        border-width: 1px;
        color: #d7e4fc;
        padding: 5px;
    }

    input[type="submit"] {
        background-color: #1e336d;
        border-color: #79a7fc;
        border-style: solid;
        border-width: 5px;
        color: #d7e4fc;
        padding: 5px 15px 5px 15px;
        font-size: 200%;
    }
*/

    body {
        color: #304050;
        font-family: arial;
    }

    input[type="submit"] {
        font-size: 150%;
        padding-left: 1em;
        padding-right: 1em;
    }

    td {
        padding: 2px 5px 2px 5px;
    }

    td.text-right {
        text-align: right;
    }

    .flex-container {
        display: flex;
        gap: 20px;
    }

    .flex-container .first {
        flex: 0 0 150px; /* Do not grow, do not shrink, lock base to 150px */
    }

    .flex-container .second {
    }

    .flex-container .second p {
        max-width: 30em;
    }

    p {
        max-width: 40em;
    }

    #pizza-logo {
        fill: #304050;
    }
</style>
<title>Install Pizza</title>
</head>
<body>
$body
</body>
</html>
HTML;
    exit();
}

function validateAdminName($adminName)
{
    if (($adminName == '') || (strlen($adminName) > 40)) return false;
    return true;
}

function validateAdminPassword($adminPassword1, $adminPassword2)
{
    // MUST be 8-50 characters and have at least one digit,
    // uppercase and lowercase letter, and CAN contain !@#$%&*
    if ($adminPassword1 != $adminPassword2) return false;
    $p = '/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])[a-zA-Z0-9!@#$%&*]{8,50}$/';
    if (!preg_match($p, $adminPassword1)) return false;
    return true;
}

function validateEmail($email)
{
    if (strlen($email) > 80) return false;
    $n = preg_match('/^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})$/', $email);
    if ($n == 0) return false;
    return true;
}

function validatePizzaDatabase($host, $pizzaDatabase, $mysqlUser, $mysqlPassword)
{
    if ((trim($pizzaDatabase) == '') || (strlen($pizzaDatabase) > 64)) return false;
    try {
        $mysqli = new mysqli($host, $mysqlUser, $mysqlPassword);
    } catch (Exception $e) {
        return false;
    }
    $mPizzaDatabase = $mysqli->real_escape_string($pizzaDatabase);
    $q = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$mPizzaDatabase'";
    $result = $mysqli->query($q);
    if (!$result) return false;
    return $result->num_rows == 0;
}

function validatePizzaUser($host, $pizzaUser, $mysqlUser, $mysqlPassword)
{
    if ((trim($pizzaUser) == '') || (strlen($pizzaUser) > 16)) return false;
    try {
        $mysqli = new mysqli($host, $mysqlUser, $mysqlPassword);
    } catch (Exception $e) {
        return false;
    }
    $mUser = $mysqli->real_escape_string($pizzaUser);
    $q = "SELECT user FROM mysql.user WHERE user = '$mUser'";
    $result = $mysqli->query($q);
    if (!$result) return false;
    return $result->num_rows == 0;
}

function validateRootMysqlUser($host, $mysqlUser, $mysqlPassword)
{
    if ((trim($mysqlUser) == '') || (strlen($mysqlUser) > 16)) return false;
    if ((trim($mysqlPassword) == '') || (strlen($mysqlPassword) > 100)) return false;
    try {
        $mysqli = new mysqli($host, $mysqlUser, $mysqlPassword);
    } catch (Exception $e) {
        return false;
    }
    return true;
}

function validateStep1(
    $host, $pizzaUser, $pizzaDatabase,
    $adminEmail, $adminName, $adminPassword1, $adminPassword2,
    $mysqlUser, $mysqlPassword
) {
    if (!validateRootMysqlUser($host, $mysqlUser, $mysqlPassword)) return false;
    if (!validatePizzaUser($host, $pizzaUser, $mysqlUser, $mysqlPassword)) return false;
    if (!validatePizzaDatabase($host, $pizzaDatabase, $mysqlUser, $mysqlPassword)) return false;
    if (!validateEmail($adminEmail)) return false;
    if (!validateAdminName($adminName)) return false;
    if (!validateAdminPassword($adminPassword1, $adminPassword2)) return false;
    return true;
}

function writeConfig($adminEmail, $host, $pizzaUser, $pizzaDatabase, $pizzaPassword)
{
    if (!file_exists('config.php.sample') || !is_readable('config.php.sample'))
        return false;
    $c = file_get_contents('config.php.sample');
    $c = str_replace("adminEmail = ''", "adminEmail = '$adminEmail'", $c);
    $c = str_replace("dbHost = ''", "dbHost = '$host'", $c);
    $c = str_replace("dbUser = ''", "dbUser = '$pizzaUser'", $c);
    $c = str_replace("dbPassword = ''", "dbPassword = '$pizzaPassword'", $c);
    $c = str_replace("dbDatabase = ''", "dbDatabase = '$pizzaDatabase'", $c);
    $result = @file_put_contents('config.php', $c);
    if ($result === false)
        exitInstalledSuccessfullyButCantWrite($adminEmail, $host, $pizzaUser, $pizzaDatabase, $pizzaPassword);
    $result = @chmod('config.php', 0644);
    if ($result === false)
        exitInstalledSuccessfullyButCantChmod();
}

$step1Complete = validateStep1(
    $host, $pizzaUser, $pizzaDatabase,
    $adminEmail, $adminName, $adminPassword1, $adminPassword2,
    $mysqlUser, $mysqlPassword
);
if (!$step1Complete)
{
    if (!$hasPizzaUser) $pizzaUser = generatePizzaUserDatabase();
    if (!$hasPizzaDatabase) $pizzaDatabase = generatePizzaUserDatabase();
    ob_start();
    ?>
<div class="flex-container">

<div class="first">
<svg id="pizza-logo" width="150" height="150" viewBox="0 0 200 200">
<g transform="translate(-372.2082,-190.88348)">
<g transform="translate(-113.50234,-246.01714)">
<path
d="m 578.96342,636.66678 c -11.27339,-0.7988 -22.38455,-3.4782 -32.7067,-7.8871 -2.89682,-1.2373 -8.16239,-3.8423 -8.9217,-4.4138 -0.45884,-0.3453 -0.32191,-0.6354 3.10946,-6.5872 1.97419,-3.4244 3.69394,-6.2966 3.82167,-6.3828 0.12773,-0.086 2.04911,0.7188 4.26974,1.7888 10.76551,5.1873 20.16287,7.6158 33.02162,8.5336 4.44856,0.3175 9.44313,0.098 15.38603,-0.6753 8.91816,-1.1609 17.35977,-3.7167 25.9551,-7.8583 2.22063,-1.07 4.14201,-1.875 4.26974,-1.7888 0.12773,0.086 1.84748,2.9584 3.82167,6.3828 3.43137,5.9518 3.5683,6.2419 3.10946,6.5872 -0.75931,0.5715 -6.02488,3.1765 -8.9217,4.4138 -14.54715,6.2135 -30.7672,8.9817 -46.21439,7.8871 z m 2.75157,-17.51 c -9.57422,-0.6784 -17.85662,-2.4961 -26.09829,-5.7276 -4.6657,-1.8295 -9.51106,-4.2268 -9.61301,-4.7563 -0.10895,-0.5658 39.27897,-68.66099 39.71521,-68.66099 0.43696,0 39.82136,68.09279 39.71222,68.65959 -0.10219,0.5307 -4.94262,2.9263 -9.61329,4.7577 -8.4572,3.3161 -16.39387,5.03 -26.59857,5.7442 -1.99489,0.1396 -3.85219,0.2388 -4.12735,0.2205 -0.27516,-0.018 -1.79477,-0.125 -3.37692,-0.2371 z m 6.5083,-83.83429 c 0.009,-0.63184 39.44748,-68.60292 39.85077,-68.68211 0.85656,-0.16821 8.20223,5.19555 13.24515,9.67152 6.84535,6.07575 14.25641,15.99521 18.43737,24.67778 4.78462,9.93625 7.93422,22.30757 8.0878,31.76808 l 0.0446,2.75157 -39.83517,0.0633 c -31.78901,0.0505 -39.83424,-10e-6 -39.83057,-0.25014 z m 82.68106,0.0838 c -0.0775,-0.12547 -0.2547,-1.67323 -0.39365,-3.43945 -0.76166,-9.68191 -2.26116,-16.73483 -5.27368,-24.80494 -6.23197,-16.69457 -17.60351,-31.00911 -32.55553,-40.98098 -1.75412,-1.16987 -3.18931,-2.2366 -3.18931,-2.37052 0,-0.33439 6.98923,-12.4353 7.28959,-12.62093 0.33042,-0.20421 3.60537,1.81729 7.32991,4.52448 12.87565,9.35867 23.10234,21.17119 30.29629,34.99426 5.39494,10.3663 9.03334,21.93155 10.47439,33.2946 0.47379,3.7359 0.97012,10.81669 0.79097,11.28361 -0.10984,0.28617 -1.42131,0.34801 -7.38078,0.34801 -4.09563,0 -7.30853,-0.0992 -7.3882,-0.22814 z m -85.77594,-2.07014 c -2.064,-3.22108 -39.17958,-67.95387 -39.12499,-68.2374 0.18655,-0.96889 10.7316,-5.49452 16.9341,-7.26762 8.62271,-2.46497 18.99867,-3.73606 26.34427,-3.22727 10.10634,0.7 18.09321,2.42681 26.53604,5.73724 4.6657,1.82942 9.51107,4.22677 9.61301,4.75625 0.087,0.45204 -39.20684,68.58085 -39.6351,68.72043 -0.1632,0.0532 -0.4635,-0.16354 -0.66733,-0.48163 z m -40.86226,-70.96179 c -0.12773,-0.0862 -1.84748,-2.95843 -3.82167,-6.38275 -3.43137,-5.95186 -3.5683,-6.24194 -3.10946,-6.58728 0.75931,-0.57147 6.02488,-3.17648 8.9217,-4.41379 3.94226,-1.68384 8.5327,-3.30025 12.51835,-4.40804 22.03672,-6.12498 45.36875,-4.57612 66.40274,4.40804 2.89682,1.23731 8.16239,3.84232 8.9217,4.41379 0.45884,0.34534 0.32191,0.63542 -3.10946,6.58728 -1.97419,3.42432 -3.69394,6.29656 -3.82167,6.38275 -0.12773,0.0862 -2.04911,-0.71875 -4.26974,-1.78875 -8.59533,-4.14162 -17.03694,-6.69746 -25.9551,-7.85835 -5.9429,-0.7736 -10.93747,-0.99281 -15.38603,-0.67529 -12.85875,0.91781 -22.25611,3.34633 -33.02162,8.53364 -2.22063,1.07 -4.14201,1.87494 -4.26974,1.78875 z" />
</g>
</g>
</svg>
</div>

<div class="second">
<form name="pizzaInstall" method="post" enctype="application/x-www-form-urlencoded" action="install">
<h1>Let’s Install Pizza!</h1>
<p>
    Your new Pizza MySQL user and database is suggested below, but you can choose your own values if you like. Also, we’ll make you the administrator. Lastly, you’ll need to know a MySQL “root” user and password so we can create your Pizza user and database.
</p>

<table border="0" cellpadding="0" cellspacing="0">
<tr>
    <td class="text-right">New MySQL User:</td>
    <td>
        <input name="pizzaInstall_user" style="width: 12em;" type="text" value="<?=myHtmlEntities($pizzaUser)?>" placeholder="New User" />
        <?php
        if ($hasPizzaUser)
        {
            if ((trim($pizzaUser) == '') || (strlen($pizzaUser) > 16))
            {
                ?>
                <span style="color: red;">←invalid new user</span>
                <?php
            }
            else if (validateRootMysqlUser($host, $mysqlUser, $mysqlPassword)
                && !validatePizzaUser($host, $pizzaUser, $mysqlUser, $mysqlPassword)
            ) {
                ?>
                <span style="color: red;">←user is invalid or already exists</span>
                <?php
            }
        }
        ?>
    </td>
</tr>

<tr>
    <td class="text-right">New MySQL Database:</td>
    <td>
        <input name="pizzaInstall_database" style="width: 12em;" type="text" value="<?=myHtmlEntities($pizzaDatabase)?>" placeholder="New Database" />
        <?php
        if ($hasPizzaDatabase)
        {
            if ((trim($pizzaDatabase) == '') || (strlen($pizzaDatabase) > 64))
            {
                ?>
                <span style="color: red;">←invalid new database</span>
                <?php
            }
            else if (validateRootMysqlUser($host, $mysqlUser, $mysqlPassword)
                && !validatePizzaDatabase($host, $pizzaDatabase, $mysqlUser, $mysqlPassword)
            ) {
                ?>
                <span style="color: red;">←database is invalid or already exists</span>
                <?php
            }
        }
        ?>
    </td>
</tr>

<tr>
    <td class="text-right" style="padding-top: 15px;">Pizza Admin Email:</td>
    <td style="padding-top: 15px;">
        <input name="pizzaInstall_adminEmail" style="width: 15em;" type="text" value="<?=myHtmlEntities($adminEmail)?>" placeholder="Email Address" />
        <?php
        if ($hasAdminEmail && !validateEmail($adminEmail))
        {
            ?>
            <span style="color: red;">←invalid email address</span>
            <?php
        }
        ?>
    </td>
</tr>

<tr>
    <td class="text-right">Pizza Admin Full Name:</td>
    <td>
        <input name="pizzaInstall_adminName" style="width: 15em;" type="text" value="<?=myHtmlEntities($adminName)?>" placeholder="First and Last Name" />
        <?php
        if ($hasAdminName && !validateAdminName($adminName))
        {
            ?>
            <span style="color: red;">←invalid full name</span>
            <?php
        }
        ?>
    </td>
</tr>

<tr>
    <td class="text-right">Pizza Admin Password:</td>
    <td>
        <input name="pizzaInstall_adminPassword1" style="width: 12em;" type="password" value="<?=myHtmlEntities($adminPassword1)?>" placeholder="New Password" />
        <?php
        if (($hasAdminPassword1 || $hasAdminPassword2) && !validateAdminPassword($adminPassword1, $adminPassword2))
        {
            ?>
            <span style="color: red;">←invalid password and/or mismatch</span>
            <?php
        }
        ?>
    </td>
</tr>

<tr>
    <td class="text-right">Confirm Password:</td>
    <td>
        <input name="pizzaInstall_adminPassword2" style="width: 12em;" type="password" value="<?=myHtmlEntities($adminPassword2)?>" placeholder="Confirm Password" />
        <?php
        if (($hasAdminPassword1 || $hasAdminPassword2) && !validateAdminPassword($adminPassword1, $adminPassword2))
        {
            ?>
            <span style="color: red;">←8-50 characters with at least 1 digit, lowercase and uppercase letter</span>
            <?php
        }
        ?>
    </td>
</tr>

<tr>
    <td class="text-right" style="padding-top: 15px;">MySQL Root User:</td>
    <td style="padding-top: 15px;">
        <input name="pizzaInstall_mysqlUser" style="width: 12em;" type="text" value="<?=myHtmlEntities($mysqlUser)?>" placeholder="Usually “root”" />
        <?php
        if (
            ($hasMysqlUser || $hasMysqlPassword)
            && !validateRootMysqlUser($host, $mysqlUser, $mysqlPassword)
        ) {
            ?>
            <span style="color: red;">←invalid root user</span>
            <?php
        }
        ?>
    </td>
</tr>

<tr>
    <td class="text-right">MySQL Root Password:</td>
    <td>
        <input name="pizzaInstall_mysqlPassword" style="width: 12em;" type="password" value="" placeholder="Password" />
        <?php
        if (
            ($hasMysqlUser || $hasMysqlPassword)
            && !validateRootMysqlUser($host, $mysqlUser, $mysqlPassword)
        ) {
            ?>
            <span style="color: red;">←and/or password</span>
            <?php
        }
        ?>
    </td>
</tr>

<tr>
    <td></td>
    <td>
        <input name="submit_step1" type="submit" value="Next" />
    </td>
</tr>
</table>
</form>
</div>

</div>
<?php
renderHtml5(ob_get_clean());
}

// step1Complete is true.

require_once('lib/password.php');
// Connect as root user.
try {
    $mysqli = new mysqli($host, $mysqlUser, $mysqlPassword);
} catch (Exception $e) {
    exitMysqlConnectError($mysqlUser);
}
// Create Pizza database.
$mPizzaDatabase = $mysqli->real_escape_string($pizzaDatabase);
$q = "CREATE DATABASE `$mPizzaDatabase` CHARACTER SET='utf8mb4' COLLATE='utf8mb4_unicode_ci'";
$result = $mysqli->query($q);
if (!$result) exitMysqlDatabaseCreationError($pizzaDatabase);
// Create Pizza user and grant priviledges for Pizza database.
$mPizzaUser = $mysqli->real_escape_string($pizzaUser);
$pizzaPassword = generatePizzaPassword();
$mPizzaPassword = $mysqli->real_escape_string($pizzaPassword);
$q = "
    CREATE USER '$mPizzaUser'@'$host' IDENTIFIED WITH caching_sha2_password BY '$mPizzaPassword'
    WITH MAX_QUERIES_PER_HOUR 0 MAX_CONNECTIONS_PER_HOUR 0 MAX_UPDATES_PER_HOUR 0
";
$result = $mysqli->query($q);
if (!$result) exitMysqlUserCreationError($pizzaUser);
$q = "
    GRANT USAGE ON *.* TO '$mPizzaUser'@'$host'
";
$result = $mysqli->query($q);
if (!$result) exitMysqlGrantUsageError($pizzaUser);
// $q = "GRANT ALL PRIVILEGES ON `$mPizzaDatabase`.* TO '$mPizzaUser'@'$host'";
$q = "
    GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER,
    CREATE TEMPORARY TABLES, LOCK TABLES, CREATE VIEW, SHOW VIEW,
    CREATE ROUTINE, ALTER ROUTINE, EXECUTE
    ON `$mPizzaDatabase`.* TO '$mPizzaUser'@'$host'
";
$result = $mysqli->query($q);
if (!$result) exitMysqlGrantDatabaseError($pizzaUser, $pizzaDatabase);
// Connect as Pizza user.
try {
    $mysqli = new mysqli($host, $pizzaUser, $pizzaPassword, $pizzaDatabase);
} catch (Exception $e) {
    exitMysqlSelectDatabaseError($pizzaUser, $pizzaDatabase);
}
// Prime the database.
$primerQueries = explode(";\n", @file_get_contents('pizza_primer.sql'));
foreach ($primerQueries as $q)
{
    $q = trim($q);
    if ($q == '') continue;
    $result = $mysqli->query($q);
    if (!$result) exitMysqlImportError($q);
}
// Insert the administrator information.
$mAdminEmail = $mysqli->real_escape_string($adminEmail);
$parts = explode(' ', $adminName, 2);
$adminFirstName = $parts[0];
$adminLastName = '';
if (count($parts) == 2) $adminLastName = $parts[1];
$mAdminFirstName = $mysqli->real_escape_string($adminFirstName);
$mAdminLastName = $mysqli->real_escape_string($adminLastName);
$hash = password_hash($adminPassword1, PASSWORD_BCRYPT);
$mHash = $mysqli->real_escape_string($hash);
$q = "
    INSERT INTO users (email, firstName, lastName, `password`, created, isSuspended, isSubscribed, notified, settings)
    VALUES ('$mAdminEmail', '$mAdminFirstName', '$mAdminLastName', '$mHash', UTC_TIMESTAMP(), 'n', 'y', UTC_TIMESTAMP() - INTERVAL 24 HOUR, '')
";
$result = $mysqli->query($q);
if (!$result) exitMysqlInsertAdministratorError($adminEmail, $adminName);
$q = "
    INSERT INTO groupMembers (groupId, userId)
    VALUES (
        (SELECT id FROM `groups` WHERE `name` = 'Administrators'),
        (SELECT id FROM users WHERE email = '$mAdminEmail')
    )
";
$result = $mysqli->query($q);
if (!$result) exitMysqlInsertAdministratorError($adminEmail, $adminName);
// Set ownership of all preloaded pages to the administrator.
$q = "
    UPDATE pages SET userId = (SELECT id FROM users WHERE email = '$mAdminEmail')
";
$result = $mysqli->query($q);
if (!$result) exitMysqlSetPageOwnershipError($adminEmail, $adminName);
// Save the info to the configuration file.
writeConfig($adminEmail, $host, $pizzaUser, $pizzaDatabase, $pizzaPassword);
exitInstalledSuccessfully();
?>
