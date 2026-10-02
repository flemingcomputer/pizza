<?php
// Copyright 2026 Fleming Computer.
// All Rights Reserved.
?>
<?php
$GLOBALS['pizza'] = array();
$GLOBALS['pizza']['docRoot'] = dirname(__FILE__);

// Pull the config.php definitions into GLOBALS['pizza']['config'].
function loadPizzaConfig()
{
    // If no config.php file, relocate to install.
    if (!is_file($GLOBALS['pizza']['docRoot'] . '/config.php'))
    {
        header('Location: install');
        exit();
    }
    // Pull in config variables.
    require('config.php');
    $GLOBALS['pizza']['config'] = get_defined_vars();
    // If (at least) dbUser isn't set, install.
    if (($GLOBALS['pizza']['config']['dbUser'] ?? '') === '')
    {
        header('Location: install');
        exit();
    }
    if (fileperms($GLOBALS['pizza']['docRoot'] . '/config.php') & 2)
        $GLOBALS['pizza']['config']['isWorldWritable'] = true;
    else
        $GLOBALS['pizza']['config']['isWorldWritable'] = false;
}
loadPizzaConfig();

// Set up the include path.
$includePath = explode(PATH_SEPARATOR, ini_get('include_path'));
while (in_array('.', $includePath)) array_shift($includePath);  // Remove through '.'
foreach (glob($GLOBALS['pizza']['docRoot'] . '/toppings/*', GLOB_ONLYDIR) as $d)
    array_unshift($includePath, $d);
unset($d);
array_unshift($includePath, $GLOBALS['pizza']['docRoot'] . '/toppings');
array_unshift($includePath, $GLOBALS['pizza']['docRoot'] . '/crust');
array_unshift($includePath, $GLOBALS['pizza']['docRoot'] . '/lib');
array_unshift($includePath, $GLOBALS['pizza']['docRoot'] . '/images');
array_unshift($includePath, $GLOBALS['pizza']['docRoot']);
array_unshift($includePath, '.');  // Put the '.' back on the front.
ini_set('include_path', implode(PATH_SEPARATOR, $includePath));
unset($includePath);
// echo ini_get('include_path'); exit();

// Set scheme, host, and requestUri based on access mode.
if (isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI']))
{
    // In web access mode, get values from _SERVER.
    $GLOBALS['pizza']['scheme'] = 'https';
    if (!isset($_SERVER['HTTPS'])) $GLOBALS['pizza']['scheme'] = 'http';
    $GLOBALS['pizza']['httpHost'] = $_SERVER['HTTP_HOST'];
    $GLOBALS['pizza']['requestUri'] = $_SERVER['REQUEST_URI'];
    $GLOBALS['pizza']['accessMode'] = 'web';
}
else
{
    // Presume command-line mode. First argument must be a valid request; exit otherwise.
    $parts = parse_url($argv[1] ?? '');
    $scheme = $parts['scheme'] ?? false;
    $host = $parts['host'] ?? '';
    $path = $parts['path'] ?? '/';
    if (($scheme === false)
        || !preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $host) //valid chars check
        || !preg_match("/^.{1,253}$/", $host) //overall length check
        || !preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $host)) //length of each label
        exit();
    $GLOBALS['pizza']['scheme'] = $scheme;
    $GLOBALS['pizza']['httpHost'] = $host;
    $GLOBALS['pizza']['requestUri'] = $path;
    $GLOBALS['pizza']['accessMode'] = 'cli';
    unset($parts); unset($scheme); unset($host); unset($path);
}

// Reserved top-level page names. These must include all .htaccess literals, as
// well as special keywords not ending in a slash e.g. login, logout, signup.
// 1. Must be all lowercase for matching strtolower on pagePath.
// 2. Add these to rememberPrevious and/or ajax/logout.php exceptions if they
//    should never be gone back to with a previousPath or previousUri call.
$GLOBALS['pizza']['reservedPages'] = array(
    '/captcha-code',
    '/install',
    '/sitemap.xml',
    '/test-email',
    '/login', // previous path exception
    '/logout',
    '/signup' // previous path exception
);

$GLOBALS['pizza']['pizzaRoot'] = basename($GLOBALS['pizza']['docRoot']);
$GLOBALS['pizza']['urlRoot'] = $GLOBALS['pizza']['scheme'] . '://' . $GLOBALS['pizza']['httpHost'];

// For testing from other devices on the LAN without having to make custom
// entries in their /etc/hosts file.  If accessing in directory mode as
// "localhost/website.com", "1.2.3.4/website.com", etc., then adjust urlRoot
// and requestUri accordingly.
if (isset($GLOBALS['pizza']['config']['lanHosts'])
    && (in_array($GLOBALS['pizza']['httpHost'], $GLOBALS['pizza']['config']['lanHosts'])))
{
    // Lengthen the urlRoot to include first component of requestUri, which
    // we presume to be the website domain name.  Likewise, shorten the
    // requestUri by that one component.
    $parts = explode('/', $GLOBALS['pizza']['requestUri'], 3);
    // First part is empty string.
    $GLOBALS['pizza']['urlRoot'] .= '/' . $parts[1];
    $GLOBALS['pizza']['requestUri'] = '/' . $parts[2];
    $GLOBALS['pizza']['pathPrefix'] = '/' . $parts[1];
    unset($parts);
}

// If showing Pizza's root directory in URLs, pizzaRoot must be the first
// path element of the requestUri.  If it isn't, then 404 the request.  Note
// that the need to 404 is only possible in an erroneous Pizza configuration,
// where .htaccess one directory up is configured to use Pizza in top-level
// domain mode but config.php isn't.
if (!$GLOBALS['pizza']['config']['hidePizzaRoot']
    && (substr($GLOBALS['pizza']['requestUri'], 0, strlen($GLOBALS['pizza']['pizzaRoot']) + 2)
        != '/' . $GLOBALS['pizza']['pizzaRoot'] . '/'))
{
    header('HTTP/1.1 404 Not Found');
    echo "<h1>Page Not Found - HTTP 404</h1>\n";
    echo "<p>\n";
    echo "Pizza is misconfigured with respect to top-level domain use.<br>\n";
    echo "The .htaccess file one directory up from Pizza is configured for ";
    echo "top-level domain use, but \$hidePizzaRoot in config.php is false.\n";
    echo "</p>\n";
    exit();
}

// If showing Pizza's root directory in URLs, shift pizzaRoot from requestUri
// onto urlRoot.
if (!$GLOBALS['pizza']['config']['hidePizzaRoot'])
{
    // Lengthen the urlRoot to include pizzaRoot.
    $GLOBALS['pizza']['urlRoot'] .= '/' . $GLOBALS['pizza']['pizzaRoot'];
    // Shorten the requestUri to be the portion following pizzaRoot.
    $GLOBALS['pizza']['requestUri'] = substr(
        $GLOBALS['pizza']['requestUri'], strlen($GLOBALS['pizza']['pizzaRoot']) + 1
    );
    $GLOBALS['pizza']['pathPrefix'] = '/' . $GLOBALS['pizza']['pizzaRoot'];
}

// The pagePath is the requestUri sans query string.
$parts = explode('?', $GLOBALS['pizza']['requestUri'], 2);
// The requestUri is URL-encoded, but we store pagePaths non-URL-encoded.  So
// URL-decode the requestUri for the purpose of obtaining the pagePath.
$GLOBALS['pizza']['pagePath'] = urldecode($parts[0]);
if (count($parts) == 2) $GLOBALS['pizza']['queryString'] = $parts[1];
unset($parts);

// echo "scheme: " . $GLOBALS['pizza']['scheme'] . "\n";
// echo "httpHost: " . $GLOBALS['pizza']['httpHost'] . "\n";
// echo "requestUri: " . $GLOBALS['pizza']['requestUri'] . "\n";
// echo "pagePath: " . $GLOBALS['pizza']['pagePath'] . "\n";
// echo "pizzaRoot: " . $GLOBALS['pizza']['pizzaRoot'] . "\n";
// echo "urlRoot: " . $GLOBALS['pizza']['urlRoot'] . "\n";
// exit();

// If this is a request for a physical non-PHP file, pass it through and quit.
$filePath = $GLOBALS['pizza']['docRoot'] . $GLOBALS['pizza']['pagePath'];
if (is_file($filePath) && (strtolower(substr($filePath, -4)) != '.php'))
{
    $fInfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($fInfo, $filePath);
    finfo_close($fInfo);
    if ($mimeType == 'text/plain')
    {
        // Needs refining for certain text file types.
        if (substr($filePath, -4) == '.css') $mimeType = 'text/css';
        else if (substr($filePath, -3) == '.js') $mimeType = 'application/javascript';
    }
    header('Content-Type: ' . $mimeType);
    echo file_get_contents($filePath);
    exit();
}
unset($filePath);

// Set initial user values.
$GLOBALS['pizza']['user'] = array();
$GLOBALS['pizza']['user']['id'] = 0;
$GLOBALS['pizza']['user']['firstName'] = '';
$GLOBALS['pizza']['user']['lastName'] = '';

// Set up a global TableIO instance.
require_once('TableIO.php');
$GLOBALS['pizza']['t'] = new TableIO(
    $GLOBALS['pizza']['config']['dbHost'],
    $GLOBALS['pizza']['config']['dbUser'],
    $GLOBALS['pizza']['config']['dbPassword'],
    $GLOBALS['pizza']['config']['dbDatabase']
);

// Update the database version if necessary.
require_once('VersionManager.php');
$vm = new VersionManager();
$vm->checkVersion();
unset($vm);

// Pull in Content Manager.
require_once('ContentManager.php');
$GLOBALS['pizza']['cm'] = new ContentManager();

// Pull in the Session framework.
require_once('Session.php');
$GLOBALS['pizza']['session'] = new Session('pizza_session');

// Pull in Settings.
require_once('Settings.php');
$GLOBALS['pizza']['settings'] = new Settings();

// Pull in Validator.
require_once('Validator.php');
$GLOBALS['pizza']['v'] = new Validator();

// Pull in Toppings.
require_once('Toppings.php');
$GLOBALS['pizza']['toppings'] = new Toppings();

// Pull in User Manager.
require_once('UserManager.php');
$GLOBALS['pizza']['um'] = new UserManager();

// Pull in Shopping Cart.
require_once('ShoppingCart.php');
$GLOBALS['pizza']['cart'] = new ShoppingCart();

// Quit here if command line access.
if (($GLOBALS['pizza']['accessMode'] ?? 'cli') == 'cli') return;

// Load the requested page information.
$GLOBALS['pizza']['page'] = $GLOBALS['pizza']['cm']->getPage($GLOBALS['pizza']['pagePath']);
// Set theme.
$GLOBALS['pizza']['theme'] = $GLOBALS['pizza']['cm']->getTheme($GLOBALS['pizza']['pagePath']);
if (substr(gge('emailTheme', ''), 0, 5) == 'email')
{
    $theme = substr(gg('emailTheme'), 0, 40);
    $themeDbUri = $GLOBALS['pizza']['cm']->pageNameToPageUri($theme);
    if (is_file("{$GLOBALS['pizza']['docRoot']}/themes/$theme/theme.html")
        || $GLOBALS['pizza']['cm']->hasPage("/themes/$themeDbUri/theme.html"))
    {
        $GLOBALS['pizza']['theme'] = $theme;
        $GLOBALS['pizza']['accessMode'] = 'html-email';
        $GLOBALS['pizza']['disableLinks'] = true;
    }
    unset($theme, $themeDbUri);
}

// If no DB-based page was found,
// and PagePath is not a reserved page,
// and pagePath is not an AJAX request,
// and pagePath does not end in '.php',  (NOT THIS ONE, top .htaccess goes to actual '.php' request)
// then error 404.
$ajaxFile = explode('/ajax/', $GLOBALS['pizza']['pagePath']);
$ajaxFile = array_pop($ajaxFile); // e.g. 'login' or '/ajaxnot/login'
$ajaxFile = $GLOBALS['pizza']['docRoot'] . '/ajax/' . $ajaxFile . '.php';
if (($GLOBALS['pizza']['page'] === false)
    && !in_array(strtolower($GLOBALS['pizza']['pagePath']), $GLOBALS['pizza']['reservedPages'])
    && !is_file($ajaxFile))
    // && (strtolower(substr($GLOBALS['pizza']['pagePath'], -4)) != '.php'))
    error404();
unset($ajaxFile);

// If pagePath represents a directory (kind != '*-file') and ends without a
// trailing slash, 301 with trailing slash.
if ((substr($GLOBALS['pizza']['pagePath'], -1) != '/')
    && isset($GLOBALS['pizza']['page']['kind']) // ['pizza']['page'] might be false
    && (substr($GLOBALS['pizza']['page']['kind'], -5) != '-file')
    && (strtolower(substr($GLOBALS['pizza']['pagePath'], -4)) != '.php')
    && !in_array(strtolower($GLOBALS['pizza']['pagePath']), $GLOBALS['pizza']['reservedPages']))
{
    $newUri = $GLOBALS['pizza']['pagePath'] . '/';
    if (isset($_SERVER['QUERY_STRING']) && ($_SERVER['QUERY_STRING'] != ''))
        $newUri .= '?' . $_SERVER['QUERY_STRING'];
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $GLOBALS['pizza']['urlRoot'] . $newUri);
    exit();
}

// If pagePath represents a file (kind == '*-file') and ends with a
// trailing slash, do 404.
if ((substr($GLOBALS['pizza']['pagePath'], -1) == '/')
    && isset($GLOBALS['pizza']['page']['kind']) // ['pizza']['page'] might be false
    && (substr($GLOBALS['pizza']['page']['kind'], -5) == '-file'))
    error404();

// // Start the NotificationManager daemon (on a db page request only is plenty).
// if ($GLOBALS['pizza']['page'] !== false)
// {
//     exec("php " . $GLOBALS['pizza']['docRoot']
//         . "/bin/NotificationManager.php " . $GLOBALS['pizza']['urlRoot'] . " >/dev/null 2>&1 &");
// }

function addJavaScript($script)
{
    if (!isset($GLOBALS['pizza']['javascripts']))
        $GLOBALS['pizza']['javascripts'] = array();
    if (!in_array($script, $GLOBALS['pizza']['javascripts']))
        $GLOBALS['pizza']['javascripts'][] = $script;
}

function addLink($rel, $type, $title, $href)
{
    $link = array($rel, $type, $title, $href);
    if (!isset($GLOBALS['pizza']['links']))
        $GLOBALS['pizza']['links'] = array();
    if (!in_array($link, $GLOBALS['pizza']['links']))
        $GLOBALS['pizza']['links'][] = $link;
}

function addStyle($stylesheet)
{
    if (!isset($GLOBALS['pizza']['stylesheets']))
        $GLOBALS['pizza']['stylesheets'] = array();
    if (!in_array($stylesheet, $GLOBALS['pizza']['stylesheets']))
        $GLOBALS['pizza']['stylesheets'][] = $stylesheet;
}

function applyTheme($body)
{
    $cm = $GLOBALS['pizza']['cm'];
    $docRoot = $GLOBALS['pizza']['docRoot'];
    $theme = sge('theme', $GLOBALS['pizza']['theme']);
    $themeDbUri = $cm->pageNameToPageUri($theme);
    $themeFileUri = urlencode($theme);
    $dbOrFileBased = 'file';
    // Search order:  default file, default db.
    if (is_file("$docRoot/themes/$theme/theme.html"))
    {
        $s = @file_get_contents("$docRoot/themes/$theme/theme.html");
    }
    else if ($cm->hasPage("/themes/$themeDbUri/theme.html"))
    {
        $dbOrFileBased = 'db';
        $page = $cm->getPage("/themes/$themeDbUri/theme.html");
        $s = $page['body'];
    }
    else
    {
        $s = "Theme \"$theme\" not found.\n";
        $s .= 'PIZZA_BODY' . "\n";
    }
    // Randomize the keywords to make cascading substitutions unlikely.
    $p = 'PIZZA' . mt_rand(1000000000, 9999999999) . '_';
    $s = str_replace('PIZZA_', $p, $s);
    $s = str_replace($p . 'BREADCRUMBS', template('breadcrumbs.php'), $s);
    $links = get_links();
    if ($links == '') // Strip entire PIZZA_LINKS line to prevent blank line.
        $s = preg_replace('/^.*' . $p . 'LINKS' . '.*$(?:\r\n|\n)?/m', '', $s);
    else
        $s = str_replace($p . 'LINKS', $links, $s);
    $s = str_replace($p . 'BAR', get_pizzaBar(), $s);
    $s = str_replace($p . 'CSS', get_stylesheets(), $s);
    $s = str_replace($p . 'JS', get_javascripts(), $s);
    $s = str_replace($p . 'LOGO', template('pizza-logo-optimized.svg'), $s);
    $s = str_replace($p . 'ROOT', $GLOBALS['pizza']['urlRoot'], $s);
    $s = str_replace($p . 'SITE_NAME', $GLOBALS['pizza']['config']['siteName'], $s);
    $s = str_replace($p . 'THEME', $dbOrFileBased == 'file' ? $themeFileUri : $themeDbUri, $s);
    // Generate the page title. Preference: H1 text, pageName, siteName.
    if (preg_match('/<h1[^>]*>([^<]+)<\/h1>/i', $body, $matches))
        $title = $matches[1]; // title is already entity encoded
    else if (isset($GLOBALS['pizza']['page']['pageName'])
        && ($GLOBALS['pizza']['page']['pageName'] != '__domain__'))
        $title = myHtmlEntities($GLOBALS['pizza']['page']['pageName']);
    else
        $title = myHtmlEntities($GLOBALS['pizza']['config']['siteName']);
    $title = '<title>' . $title . '</title>';
    $s = str_replace($p . 'TITLE', $title, $s);
    // Insert OpenGraph headers before <title> in head section.
    if (($GLOBALS['pizza']['accessMode'] == 'web')
        && isset($GLOBALS['pizza']['page']['openGraph']))
    {
        $ogTitle = $GLOBALS['pizza']['page']['openGraph']['title'];
        $ogDescription = $GLOBALS['pizza']['page']['openGraph']['description'];
        $ogImage = $GLOBALS['pizza']['page']['openGraph']['image'];
        $ogUrl = $GLOBALS['pizza']['page']['openGraph']['url'];
        $ogType = $GLOBALS['pizza']['page']['openGraph']['type'];
        ob_start();
        echo <<<OPENGRAPH
<!-- OpenGraph headers -->
<meta property="og:title" content="$ogTitle" />
<meta property="og:description" content="$ogDescription" />
<meta property="og:image" content="$ogImage" />
<meta property="og:url" content="$ogUrl" />
<meta property="og:type" content="$ogType" />
OPENGRAPH;
        $headers = ob_get_clean();
        // Apply any indentation preceding <title> to all non-first header lines.
        $indent = '';
        if (preg_match('/^(\s*)<title>.*<\/title>/m', $s, $matches))
            $indent = $matches[1];
        $headers = str_replace("\n", "\n$indent", $headers);
        $s = preg_replace('/(<title>.*<\/title>)/', $headers . "\n" . $indent . '$1', $s, 1);
    }
    // Insert any page-specific headers before <title> in head section.
    if (isset($GLOBALS['pizza']['page']['head']) && ($GLOBALS['pizza']['accessMode'] == 'web'))
    {
        $headers = "<!-- page-specific headers -->\n" . $GLOBALS['pizza']['page']['head'];
        // Apply any indentation preceding <title> to all non-first header lines.
        $indent = '';
        if (preg_match('/^(\s*)<title>.*<\/title>/m', $s, $matches))
            $indent = $matches[1];
        $headers = str_replace("\n", "\n$indent", $headers);
        $s = preg_replace('/(<title>.*<\/title>)/', $headers . "\n" . $indent . '$1', $s, 1);
    }
    $views = $GLOBALS['pizza']['page']['views'] ?? 0;
    $views = $views > 1 ? number_format($views) . ' views' : '';
    $s = str_replace($p . 'VIEWS', $views, $s);
    $s = str_replace($p . 'YEAR', date('Y'), $s);
    $s = str_replace($p . 'BODY', $body, $s);
    // If pathPrefix is set, augment body tag for directory-based usage from client side.
    if (isset($GLOBALS['pizza']['pathPrefix']))
        $s = preg_replace('/<body(.*)>/',
            '<body data-path-prefix="'. $GLOBALS['pizza']['pathPrefix'] . '"$1>', $s);
    // Make relative URLs beginning with '/' absolute, ...
    $urlRoot = $GLOBALS['pizza']['urlRoot'];
    $pagePath = $GLOBALS['pizza']['pagePath'];
    $s = preg_replace('~\s+(?:action|href|poster|src)=[\'"]\K/[^\'"]*~',
        $urlRoot . "$0", $s);
    // ...then, make relative URLs not beginning with '/' absolute, ...
    $s = preg_replace('~\s+(?:action|href|poster|src)=[\'"](?!https?://)(?!ftp://)(?!mailto:)(?!tel:)(?!unityhub://)(?!banter://)\K[^/][^\'"]*~',
        $urlRoot . $pagePath . "$0", $s);
    // The below almost works but is problematic in that it can back up so much
    // as to remove the domain name too, leaving https:// as an example.
    // Browsers handle these ../ paths just fine, so leave it alone.
    //
    // // ...then, convert "/xx/../yy" to "/yy" in URLs. Loop because of /../../ ...
    // // (This regex replaces xx/../ with nothing, which leaves /yy)
    // do
    //     $s = preg_replace('~\s+(?:action|href|poster|src)=[\'"][^>]*\K[^/]+/\.\./~U', '', $s, -1, $count);
    // while ($count);
    if ($GLOBALS['pizza']['accessMode'] == 'web')
        header('Content-Type: text/html; charset=utf-8');
    if ($GLOBALS['pizza']['disableLinks'] ?? false)
    {
        $s = preg_replace('~\s+(?:action|href)=[\'"]\K[^\'"]*~', "javascript: void(0)", $s);
        $s = preg_replace('~\s+target\s*=\s*"_blank"~', '', $s);
    }
    // Check for world-writable config.php warning.
    if ($GLOBALS['pizza']['config']['isWorldWritable'] ?? true)
    {
        $warningHtml = '<p style="background-color: black; color: red; padding: 1px 10px 1px 10px; width: fit-content;">config.php is world-writable</p>';
        $s = preg_replace('/<body(.*)>/',
            "<body$1>\n" . $warningHtml, $s);
    }
    return $s;
}

function beginSession($mode = '')
{
    $GLOBALS['pizza']['session']->beginSession($mode);
}

function currentPath()
{
    return sge('currentPath', '/');
}

function currentUri()
{
    return sge('currentUri', '/');
}

function error404()
{
    // Begin session if not started but it exists; i.e. show account menu.
    header('HTTP/1.1 404 Not Found');
    $html = applyTheme(template('error404.php'));
    // $html = str_replace('</body>', getDebugInfo() . '</body>', $html);
    echo $html;
    exit();
}

function errorPageChanged()
{
    $html = applyTheme(template('errorPageChanged.php'));
    // $html = str_replace('</body>', getDebugInfo() . '</body>', $html);
    echo $html;
    exit();
}

function errorPageLocked()
{
    $html = applyTheme(template('errorPageLocked.php'));
    // $html = str_replace('</body>', getDebugInfo() . '</body>', $html);
    echo $html;
    exit();
}

function errorUnauthorized()
{
    $html = applyTheme(template('errorUnauthorized.php'));
    // $html = str_replace('</body>', getDebugInfo() . '</body>', $html);
    echo $html;
    exit();
}

function fileExistsInIncludePath($file)
{
    $s = @file_get_contents($file, FILE_USE_INCLUDE_PATH, NULL, 0, 0);
    return $s !== false;
}

function getClientIp()
{
    if (isset($_SERVER['REMOTE_ADDR']) && ($_SERVER['REMOTE_ADDR'] != ''))
    {
        $ips = explode(',', $_SERVER['REMOTE_ADDR'], 2);
        $ip = $ips[0];
    }
    else if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])
        && ($_SERVER['HTTP_X_FORWARDED_FOR'] != ''))
    {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'], 2);
        $ip = $ips[0];
    }
    else return false;
    return $ip;
}

function getDebugInfo()
{
    $pizzaState = $GLOBALS['pizza'];
    ksort($pizzaState);
    ob_start();
    ?>
<div style="clear: both; float: left; margin-top: 50px; width: 100%;">
<xmp>
<?php print_r($pizzaState); ?>
</xmp>
</div>
<?php
    $debugInfo = ob_get_clean();
    return $debugInfo;
}

function get_javascripts()
{
    if ($GLOBALS['pizza']['accessMode'] == 'html-email')
        return "<!-- email mode, javascripts omitted -->";
    $cm = $GLOBALS['pizza']['cm'];
    $docRoot = $GLOBALS['pizza']['docRoot'];
    $urlRoot = $GLOBALS['pizza']['urlRoot'];
    $theme = sge('theme', $GLOBALS['pizza']['theme']);
    $themeDbUri = $cm->pageNameToPageUri($theme);
    $themeFileUri = urlencode($theme);
    ob_start();
    // Add jQuery, jQuery UI, and system.
    echo <<<HTML
    <!-- jQuery, jQuery UI, and Pizza js -->
    <script src="$urlRoot/js/jquery.min.js"></script>
    <script src="$urlRoot/js/jquery-ui/jquery-ui.min.js"></script>
    <script src="$urlRoot/js/jquery.ui.touch-punch.min.js"></script>
    <script src="$urlRoot/js/pizza.js"></script>\n
HTML;
    // Add toppings (addJavaScript()).
    if (isset($GLOBALS['pizza']['javascripts']))
    {
        echo <<<HTML
    <!-- components js -->\n
HTML;
        foreach ($GLOBALS['pizza']['javascripts'] as $script)
            echo <<<HTML
    <script src="$script"></script>\n
HTML;
    }
    // Search order:  default file, default db.
    if (is_file("$docRoot/themes/$theme/theme.js"))
    {
        echo <<<HTML
    <!-- theme js -->\n
HTML;
        echo <<<HTML
    <script src="$urlRoot/themes/$themeFileUri/theme.js"></script>
HTML;
    }
    else if ($cm->hasPage("/themes/$themeDbUri/theme.js"))
    {
        echo <<<HTML
    <!-- theme js -->\n
HTML;
        echo <<<HTML
    <script src="$urlRoot/themes/$themeDbUri/theme.js"></script>
HTML;
    }
    return trim(ob_get_clean());
}

function get_links()
{
    ob_start();
    if (isset($GLOBALS['pizza']['links']))
    {
        echo <<<HTML
    <!-- components links -->\n
HTML;
        foreach ($GLOBALS['pizza']['links'] as $link)
        {
            $rel = $link[0];
            $type = $link[1];
            $title = myHtmlEntities($link[2]);
            $href = $link[3];
            echo <<<HTML
    <link rel="$rel" type="$type" title="$title" href="$href">\n
HTML;
        }
    }
    return trim(ob_get_clean());
}

function get_pizzaBar()
{
    if ($GLOBALS['pizza']['accessMode'] == 'html-email') return '';
    $pagePath = $GLOBALS['pizza']['pagePath'];
    if (!hs("PizzaBar_$pagePath"))
    {
        require_once('PizzaBar.php');
        $pb = new PizzaBar();
    }
    else $pb = sg("PizzaBar_$pagePath");
    $bar = '<div class="rwd-debug"></div>' . "\n";
    $bar .= $pb->getHtml();
    return $bar;
}

function get_stylesheets()
{
    $cm = $GLOBALS['pizza']['cm'];
    $docRoot = $GLOBALS['pizza']['docRoot'];
    $urlRoot = $GLOBALS['pizza']['urlRoot'];
    $theme = sge('theme', $GLOBALS['pizza']['theme']);
    $themeDbUri = $cm->pageNameToPageUri($theme);
    $themeFileUri = urlencode($theme);
    // Insert order:
    // 1.  jQuery UI, which is overridden by
    // 2.  system default, which is overridden by
    // 3.  toppings (addStyle()), which is overridden by
    // 4.  theme.
    if ($GLOBALS['pizza']['accessMode'] == 'web')
    {
        ob_start();
        echo <<<HTML
        <!-- jQuery UI css -->
            <link rel="stylesheet" type="text/css" href="$urlRoot/js/jquery-ui/jquery-ui.min.css">
            <!-- default css -->
            <link rel="stylesheet" type="text/css" href="$urlRoot/css/default.css">\n
        HTML;
        // Add the toppings (addStyle()) stylesheets.
        if (isset($GLOBALS['pizza']['stylesheets']))
        {
            echo <<<HTML
                <!-- components css -->\n
            HTML;
            foreach ($GLOBALS['pizza']['stylesheets'] as $stylesheet)
                echo <<<HTML
                    <link rel="stylesheet" type="text/css" href="$stylesheet">\n
                HTML;
        }
        // Search order: default file, default db.
        echo <<<HTML
            <!-- theme css -->\n
        HTML;
        if (is_file("$docRoot/themes/$theme/theme.css"))
        {
            echo <<<HTML
                <link rel="stylesheet" type="text/css" href="$urlRoot/themes/$themeFileUri/theme.css">\n
            HTML;
        }
        else if ($cm->hasPage("/themes/$themeDbUri/theme.css"))
        {
            echo <<<HTML
                <link rel="stylesheet" type="text/css" href="$urlRoot/themes/$themeDbUri/theme.css">\n
            HTML;
        }
        return trim(ob_get_clean());
    }
    if ($GLOBALS['pizza']['accessMode'] == 'html-email')
    {
        $docRoot = $GLOBALS['pizza']['docRoot'];
        ob_start();
        // Add the toppings (addStyle()) stylesheets.
        if (isset($GLOBALS['pizza']['stylesheets']))
        {
            echo <<<HTML
                <!-- components css -->\n
            HTML;
            foreach ($GLOBALS['pizza']['stylesheets'] as $stylesheet)
            {
                $css = trim(@file_get_contents($stylesheet));
                echo <<<HTML
                    <style>
                    $css
                    </style>\n
                HTML;
            }
        }
        // Search order: default file, default db.
        echo <<<HTML
            <!-- theme css -->\n
        HTML;
        if (is_file("$docRoot/themes/$theme/theme.css"))
        {
            $css = trim(@file_get_contents("$docRoot/themes/$theme/theme.css"));
            echo <<<HTML
                <style>
                $css
                </style>\n
            HTML;
        }
        else if ($cm->hasPage("/themes/$themeDbUri/theme.css"))
        {
            $css = $cm->getPage("/themes/$themeDbUri/theme.css");
            $css = trim($css['body']);
            echo <<<HTML
                <style>
                $css
                </style>
            HTML;
        }
        return trim(ob_get_clean());
    }
    return '';
}

function gg($field)
{
    if (isset($_GET[$field])) return $_GET[$field];
    return false;
}

function gge($field, $default)
{
    if (isset($_GET[$field])) return $_GET[$field];
    return $default;
}

function hg($field)
{
    return isset($_GET[$field]);
}

function hp($field)
{
    return isset($_POST[$field]);
}

function hs($field)
{
    return $GLOBALS['pizza']['session']->hs($field);
}

function inSitemap($pagePath = false)
{
    return $GLOBALS['pizza']['cm']->inSitemap($pagePath);
}

function isAdministrator()
{
    return $GLOBALS['pizza']['cm']->isAdministrator();
}

function isOwner($pagePath = false)
{
    return $GLOBALS['pizza']['cm']->isOwner($pagePath);
}

function isReadable($pagePath = false)
{
    return $GLOBALS['pizza']['cm']->isReadable($pagePath);
}

function isTurnedOn($pagePath = false)
{
    return $GLOBALS['pizza']['cm']->isTurnedOn($pagePath);
}

function isWritable($pagePath = false)
{
    return $GLOBALS['pizza']['cm']->isWritable($pagePath);
}

function lockedBy()
{
    return $GLOBALS['pizza']['cm']->lockedBy();
}

function lockPage()
{
    return $GLOBALS['pizza']['cm']->lockPage();
}

function login($user)
{
    $GLOBALS['pizza']['cm']->unlockPages($user['id']);
    ss('user', $user);
}

function logout()
{
    $cart = $GLOBALS['pizza']['cart']->getCart();
    $GLOBALS['pizza']['cm']->unlockPages($GLOBALS['pizza']['user']['id']);
    resetSession();
    $GLOBALS['pizza']['user'] = array();
    $GLOBALS['pizza']['user']['id'] = 0;
    $GLOBALS['pizza']['user']['firstName'] = '';
    $GLOBALS['pizza']['user']['lastName'] = '';
    $GLOBALS['pizza']['cart']->setCart($cart);
}

function myHtmlEntities($s)
{
    // // Haven't used this one for years.
    // // Encode all entities including single quotes (ENT_QUOTES), and double-encode.
    // // This converts newlines to &NewLine;
    // $s = htmlentities($s, ENT_QUOTES, 'UTF-8');

    // // This is the one I've used for years. Problem is that it was showing
    // // literal things like &nbsp; as rendered spaces on re-editing. So you'd
    // // lose the orignal literal entity coding.
    // // Encode &, ", ', <, >, but don't double-encode.
    // $s = htmlspecialchars($s, ENT_QUOTES, 'UTF-8', false);

    // This is the latest attempt. It preserves literal things like &nbsp;
    // Encode &, ", ', <, >, and double-encode.
    $s = htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8', true);

    return $s;
}

function pg($field)
{
    if (isset($_POST[$field])) return $_POST[$field];
    return false;
}

function pge($field, $default)
{
    if (isset($_POST[$field])) return $_POST[$field];
    return $default;
}

function prettify($html)
{
    $html = preg_replace('/\n\h+/', "\n", $html);
    return $html;
    // Or instead...
    // Use Tidy to pretty-print the output.
    // Tidy does not know HTML5 very well, such as <meta> being atomic, etc.
    $config = array(
        'indent' => true,
        'indent-spaces' => 2,
        'input-xml' => true,
        'output-xml' => true,
        'wrap' => false
    );
    $tidy = tidy_parse_string($html, $config, 'utf8');
    $html = tidy_get_output($tidy);
    // Prepend the body with any Tidy errors.
    if ((tidy_error_count($tidy) > 0) || (tidy_warning_count($tidy) > 0))
    {
        $errors = tidy_get_error_buffer($tidy);
        $errors = "<xmp style=\"background-color: white;\">Tidy Errors:\n$errors</xmp>";
        $html = preg_replace('/<body.*>/U', "$0\n$errors", $html, 1);
    }
    return $html;
}

function previousPath()
{
    return sge('previousPath', '/');
}

function previousUri()
{
    return sge('previousUri', '/');
}

function relocateNow($url)
{
    header("Location: $url");
    exit();
}

function rememberPrevious()
{
    $currentPath = $GLOBALS['pizza']['pagePath'];
    $currentUri = $GLOBALS['pizza']['requestUri'];
    if (hs('currentPath')
        && (strtolower(sg('currentPath')) == strtolower($currentPath)))
    {
        // We went to the same page as before, but possibly different query string.
        if (sg('currentUri') === $currentUri) return;
        // Query string has changed; update currentUri.
        ss('currentUri', $currentUri);
        return;
    };
    // Update previous and current paths.
    $oldPath = sge('currentPath', '/');
    $oldUri = sge('currentUri', '/');
    if ((strtolower($oldPath) == '/login')
        || (strtolower($oldPath) == '/profile/')
        || (strtolower(substr($oldPath, 0, 10)) == '/settings/')
        || (strtolower($oldPath) == '/signup'))
    {
        $oldPath = '/';
        $oldUri = '/';
    }
    ss('previousPath', $oldPath);
    ss('previousUri', $oldUri);
    ss('currentPath', $currentPath);
    ss('currentUri', $currentUri);
}

function resetSession()
{
    $GLOBALS['pizza']['session']->resetSession();
}

function safeFileName($fileName)
{
    $safeFileName = preg_replace('/[^A-Za-z0-9?![:space:]\.\-_]/', '_',
        pathinfo($fileName, PATHINFO_FILENAME));
    $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
    if ($safeFileName == '' || $safeFileName[0] == '.')
        $safeFileName = 'unnamed_file';
    return $safeFileName . '.' . $fileExtension;
}

function sc($field1)
{
    $fields = array();
    if (func_num_args() > 1) $fields = array_slice(func_get_args(), 1);
    array_unshift($fields, $field1);
    foreach ($fields as $field)
        $GLOBALS['pizza']['session']->sc($field);
}

function sessionExists()
{
    return $GLOBALS['pizza']['session']->sessionExists();
}

function sg($field)
{
    return $GLOBALS['pizza']['session']->sg($field);
}

function sge($field, $default)
{
    return $GLOBALS['pizza']['session']->sge($field, $default);
}

function showFinal($body)
{
    $html = applyTheme($body);
    echo $html;
    exit();
}

function ss($field, $value)
{
    $GLOBALS['pizza']['session']->ss($field, $value);
}

function storageRoot()
{
    $storageRoot = $GLOBALS['pizza']['config']['storage']['root'] ?? false;
    if ($storageRoot === false) return false;
    if (substr($storageRoot, 0, 1) != '/')
        $storageRoot = $GLOBALS['pizza']['docRoot'] . '/' . $storageRoot;
    $storageRoot = realpath($storageRoot);
    if ($storageRoot === false) return false;
    $siteRoot = basename(dirname($GLOBALS['pizza']['docRoot']));
    return $storageRoot . DIRECTORY_SEPARATOR . $siteRoot;
}

function template($templateFile, $args = array())
{
    foreach ($args as $k => $v) $$k = $v;
    ob_start();
    require($templateFile);
    return ob_get_clean();
}

function unlockPage()
{
    return $GLOBALS['pizza']['cm']->unlockPage();
}

$GLOBALS['pizza']['countries'] = array(
'AF'=>'Afghanistan',
'AX'=>'Åland Islands',
'AL'=>'Albania',
'DZ'=>'Algeria',
'AS'=>'American Samoa',
'AD'=>'Andorra',
'AO'=>'Angola',
'AI'=>'Anguilla',
'AQ'=>'Antarctica',
'AG'=>'Antigua and Barbuda',
'AR'=>'Argentina',
'AM'=>'Armenia',
'AW'=>'Aruba',
'AU'=>'Australia',
'AT'=>'Austria',
'AZ'=>'Azerbaijan',
'BS'=>'Bahamas',
'BH'=>'Bahrain',
'BD'=>'Bangladesh',
'BB'=>'Barbados',
'BY'=>'Belarus',
'BE'=>'Belgium',
'BZ'=>'Belize',
'BJ'=>'Benin',
'BM'=>'Bermuda',
'BT'=>'Bhutan',
'BO'=>'Bolivia, Plurinational State of',
'BQ'=>'Bonaire, Sint Eustatius and Saba',
'BA'=>'Bosnia and Herzegovina',
'BW'=>'Botswana',
'BV'=>'Bouvet Island',
'BR'=>'Brazil',
'IO'=>'British Indian Ocean Territory',
'BN'=>'Brunei Darussalam',
'BG'=>'Bulgaria',
'BF'=>'Burkina Faso',
'BI'=>'Burundi',
'KH'=>'Cambodia',
'CM'=>'Cameroon',
'CA'=>'Canada',
'CV'=>'Cape Verde',
'KY'=>'Cayman Islands',
'CF'=>'Central African Republic',
'TD'=>'Chad',
'CL'=>'Chile',
'CN'=>'China',
'CX'=>'Christmas Island',
'CC'=>'Cocos (Keeling) Islands',
'CO'=>'Colombia',
'KM'=>'Comoros',
'CG'=>'Congo',
'CD'=>'Congo, the Democratic Republic of the',
'CK'=>'Cook Islands',
'CR'=>'Costa Rica',
'CI'=>'Côte d\'Ivoire',
'HR'=>'Croatia',
'CU'=>'Cuba',
'CW'=>'Curaçao',
'CY'=>'Cyprus',
'CZ'=>'Czech Republic',
'DK'=>'Denmark',
'DJ'=>'Djibouti',
'DM'=>'Dominica',
'DO'=>'Dominican Republic',
'EC'=>'Ecuador',
'EG'=>'Egypt',
'SV'=>'El Salvador',
'GQ'=>'Equatorial Guinea',
'ER'=>'Eritrea',
'EE'=>'Estonia',
'ET'=>'Ethiopia',
'FK'=>'Falkland Islands (Malvinas)',
'FO'=>'Faroe Islands',
'FJ'=>'Fiji',
'FI'=>'Finland',
'FR'=>'France',
'GF'=>'French Guiana',
'PF'=>'French Polynesia',
'TF'=>'French Southern Territories',
'GA'=>'Gabon',
'GM'=>'Gambia',
'GE'=>'Georgia',
'DE'=>'Germany',
'GH'=>'Ghana',
'GI'=>'Gibraltar',
'GR'=>'Greece',
'GL'=>'Greenland',
'GD'=>'Grenada',
'GP'=>'Guadeloupe',
'GU'=>'Guam',
'GT'=>'Guatemala',
'GG'=>'Guernsey',
'GN'=>'Guinea',
'GW'=>'Guinea-Bissau',
'GY'=>'Guyana',
'HT'=>'Haiti',
'HM'=>'Heard Island and McDonald Islands',
'VA'=>'Holy See (Vatican City State)',
'HN'=>'Honduras',
'HK'=>'Hong Kong',
'HU'=>'Hungary',
'IS'=>'Iceland',
'IN'=>'India',
'ID'=>'Indonesia',
'IR'=>'Iran, Islamic Republic of',
'IQ'=>'Iraq',
'IE'=>'Ireland',
'IM'=>'Isle of Man',
'IL'=>'Israel',
'IT'=>'Italy',
'JM'=>'Jamaica',
'JP'=>'Japan',
'JE'=>'Jersey',
'JO'=>'Jordan',
'KZ'=>'Kazakhstan',
'KE'=>'Kenya',
'KI'=>'Kiribati',
'KP'=>'Korea, Democratic People\'s Republic of',
'KR'=>'Korea, Republic of',
'KW'=>'Kuwait',
'KG'=>'Kyrgyzstan',
'LA'=>'Lao People\'s Democratic Republic',
'LV'=>'Latvia',
'LB'=>'Lebanon',
'LS'=>'Lesotho',
'LR'=>'Liberia',
'LY'=>'Libya',
'LI'=>'Liechtenstein',
'LT'=>'Lithuania',
'LU'=>'Luxembourg',
'MO'=>'Macao',
'MK'=>'Macedonia, the former Yugoslav Republic of',
'MG'=>'Madagascar',
'MW'=>'Malawi',
'MY'=>'Malaysia',
'MV'=>'Maldives',
'ML'=>'Mali',
'MT'=>'Malta',
'MH'=>'Marshall Islands',
'MQ'=>'Martinique',
'MR'=>'Mauritania',
'MU'=>'Mauritius',
'YT'=>'Mayotte',
'MX'=>'Mexico',
'FM'=>'Micronesia, Federated States of',
'MD'=>'Moldova, Republic of',
'MC'=>'Monaco',
'MN'=>'Mongolia',
'ME'=>'Montenegro',
'MS'=>'Montserrat',
'MA'=>'Morocco',
'MZ'=>'Mozambique',
'MM'=>'Myanmar',
'NA'=>'Namibia',
'NR'=>'Nauru',
'NP'=>'Nepal',
'NL'=>'Netherlands',
'NC'=>'New Caledonia',
'NZ'=>'New Zealand',
'NI'=>'Nicaragua',
'NE'=>'Niger',
'NG'=>'Nigeria',
'NU'=>'Niue',
'NF'=>'Norfolk Island',
'MP'=>'Northern Mariana Islands',
'NO'=>'Norway',
'OM'=>'Oman',
'PK'=>'Pakistan',
'PW'=>'Palau',
'PS'=>'Palestinian Territory, Occupied',
'PA'=>'Panama',
'PG'=>'Papua New Guinea',
'PY'=>'Paraguay',
'PE'=>'Peru',
'PH'=>'Philippines',
'PN'=>'Pitcairn',
'PL'=>'Poland',
'PT'=>'Portugal',
'PR'=>'Puerto Rico',
'QA'=>'Qatar',
'RE'=>'Réunion',
'RO'=>'Romania',
'RU'=>'Russian Federation',
'RW'=>'Rwanda',
'BL'=>'Saint Barthélemy',
'SH'=>'Saint Helena, Ascension and Tristan da Cunha',
'KN'=>'Saint Kitts and Nevis',
'LC'=>'Saint Lucia',
'MF'=>'Saint Martin (French part)',
'PM'=>'Saint Pierre and Miquelon',
'VC'=>'Saint Vincent and the Grenadines',
'WS'=>'Samoa',
'SM'=>'San Marino',
'ST'=>'Sao Tome and Principe',
'SA'=>'Saudi Arabia',
'SN'=>'Senegal',
'RS'=>'Serbia',
'SC'=>'Seychelles',
'SL'=>'Sierra Leone',
'SG'=>'Singapore',
'SX'=>'Sint Maarten (Dutch part)',
'SK'=>'Slovakia',
'SI'=>'Slovenia',
'SB'=>'Solomon Islands',
'SO'=>'Somalia',
'ZA'=>'South Africa',
'GS'=>'South Georgia and the South Sandwich Islands',
'SS'=>'South Sudan',
'ES'=>'Spain',
'LK'=>'Sri Lanka',
'SD'=>'Sudan',
'SR'=>'Suriname',
'SJ'=>'Svalbard and Jan Mayen',
'SZ'=>'Swaziland',
'SE'=>'Sweden',
'CH'=>'Switzerland',
'SY'=>'Syrian Arab Republic',
'TW'=>'Taiwan, Province of China',
'TJ'=>'Tajikistan',
'TZ'=>'Tanzania, United Republic of',
'TH'=>'Thailand',
'TL'=>'Timor-Leste',
'TG'=>'Togo',
'TK'=>'Tokelau',
'TO'=>'Tonga',
'TT'=>'Trinidad and Tobago',
'TN'=>'Tunisia',
'TR'=>'Turkey',
'TM'=>'Turkmenistan',
'TC'=>'Turks and Caicos Islands',
'TV'=>'Tuvalu',
'UG'=>'Uganda',
'UA'=>'Ukraine',
'AE'=>'United Arab Emirates',
'GB'=>'United Kingdom',
'US'=>'United States',
'UM'=>'United States Minor Outlying Islands',
'UY'=>'Uruguay',
'UZ'=>'Uzbekistan',
'VU'=>'Vanuatu',
'VE'=>'Venezuela, Bolivarian Republic of',
'VN'=>'Viet Nam',
'VG'=>'Virgin Islands, British',
'VI'=>'Virgin Islands, U.S.',
'WF'=>'Wallis and Futuna',
'EH'=>'Western Sahara',
'YE'=>'Yemen',
'ZM'=>'Zambia',
'ZW'=>'Zimbabwe'
);
?>
