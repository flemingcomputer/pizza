<?php
// Copyright 2022 Fleming Computer.
// All Rights Reserved.
?>
<?php
require_once('pizza.php');
require_once('DaemonManager.php');
require_once('MailTools.php');

class NotificationManager
{
    private $cm;
    private $dm;
    private $mt;
    private $t;
    private $tp;
    private $um;

    private $svgImages;
    private $i;

    function __construct()
    {
        $this->cm = $GLOBALS['pizza']['cm'];
        $this->dm = new DaemonManager();
        $this->mt = new MailTools();
        $this->t = $GLOBALS['pizza']['t'];
        $this->tp = $GLOBALS['pizza']['toppings'];
        $this->um = $GLOBALS['pizza']['um'];
        $this->svgImages = array();
        $this->i = 0;
    }

    function doNotifications()
    {
        global $argv;
        $mode = $argv[2] ?? '';
        if ($mode != 'all') $mode = 'test';
        $theme = $argv[3] ?? false;
        if ($theme === false) return false;
        $themes = $this->cm->getThemes();
        if (!in_array($theme, $themes)) return false;
        $pagePath = $argv[4] ?? 0;
        $altUrl = $argv[5] ?? 'none';
        $testEmails = $argv[6] ?? '';
        if (!$this->dm->launch(get_class($this), 1, 10)) return false;
        // echo 'Testing: calling update() every second for 122 seconds -- |';
        // for ($i = 1; $i <= 122; $i++)
        // {
        //     sleep(1);
        //     $this->dm->update();
        //     if ($i % 60 == 0) echo '*';
        //     else if ($i % 10 == 0) echo '|';
        //     else echo '.';
        // }
        // echo "\n";
        // echo "Testing: quitting (simulated crash).\n";
        // exit();
        echo "Doing notifications...\n";
        // Filter for subscribed users.
        $subscribed = array_filter($this->um->getUsers(), function($u) {
            return ($u['isSuspended'] == 'n') && ($u['isSubscribed'] == 'y');
        });
        $recipients = $subscribed;
        if ($mode == 'test')
        {
            $recipients = array();
            $testEmails = explode(',', $testEmails);
            foreach ($testEmails as $email)
            {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
                $user = array_filter($subscribed, function($u) use ($email) {
                    return $u['email'] == $email;
                });
                if (!empty($user))
                {
                    $user = array_pop($user);
                    $recipients[] = array(
                        'firstName' => $user['firstName'],
                        'lastName' => $user['lastName'],
                        'email' => $email,
                        'isSubscribed' => 'y'
                    );
                }
                else
                {
                    $recipients[] = array(
                        'firstName' => '',
                        'lastName' => '',
                        'email' => $email
                    );
                }
            }
        }
        // Send message to filtered users.
        $page = $this->cm->getPage($pagePath);
        $text = $this->getNotificationText($pagePath, $altUrl);
        $textSubscribed = $this->getNotificationTextSubscribed($pagePath, $altUrl);
        $html = $this->getNotificationHtml($theme, $page, $pagePath, $altUrl);
        $htmlSubscribed = $this->getNotificationHtmlSubscribed($theme, $page, $pagePath, $altUrl);
        // Generate the subject. Preference: H1 text, pageName, siteName.
        if (preg_match('/<h1[^>]*>([^<]+)<\/h1>/i', $html, $matches))
        {
            // Subject is already entity encoded and must be decoded for quotes etc.
            $subject = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        else
        {
            $subject = $page['pageName'];
            if ($subject == '__domain__')
                $subject = 'Update on ' . $GLOBALS['pizza']['config']['siteName'];
        }
        $this->mt->setSubject($subject);
        $this->mt->setFrom('info@' . strtolower($GLOBALS['pizza']['config']['siteName']));
        $lastDomain = '';
        foreach ($recipients as $r)
        {
            $firstName = $r['firstName'] ?? '';
            $lastName = $r['lastName'] ?? '';
            $email = $r['email'];
            $to = $email;
            if (($firstName != '') || ($lastName != ''))
                $to = "$firstName $lastName <$email>";
            $this->mt->setTo($to);
            if (isset($r['isSubscribed'])) // Is only set if 'y' here.
            {
                $this->mt->setBody($textSubscribed);
                $this->mt->setHtmlBody($htmlSubscribed);
            }
            else
            {
                $this->mt->setBody($text);
                $this->mt->setHtmlBody($html);
            }
            $parts = explode('@', $email);
            $domain = array_pop($parts);
            if ($domain == $lastDomain) sleep(2); // Don't bombard same server.
            $lastDomain = $domain;
            $result = $this->mt->send();
            if ($result) echo "Sent notification to $to.\n";
            else echo "Failed attempt to notify $to.\n";
            if (isset($r['id'])) // Might be sending a test to a non-user's email.
            {
                // Update this user as being notified.
                $now = gmdate('Y-m-d H:i:s');
                $q = "UPDATE users SET notified = '$now' WHERE id = {$r['id']}";
                $this->t->query($q);
            }
            $this->dm->update();
        }
        $this->dm->shutdown();
        echo "Done.\n";
    }

    private function getNotificationHtml($theme, $page, $pagePath, $altUrl)
    {
        $GLOBALS['pizza']['pagePath'] = $pagePath;
        $GLOBALS['pizza']['page'] = $page;
        $GLOBALS['pizza']['accessMode'] = 'html-email';
        $GLOBALS['pizza']['theme'] = $theme;
        $body = $this->tp->applyToppings($GLOBALS['pizza']['page']['body']);
        $body = $this->toHtmlEmail($GLOBALS['pizza']['pagePath'], $body, $altUrl);
        // Add 'read in browser' link.
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $siteName = strtolower($GLOBALS['pizza']['config']['siteName']);
        $url = $urlRoot . $GLOBALS['pizza']['pagePath'];
        if ($altUrl != 'none') $url = $altUrl;
        $body = '<p style="text-align: center;"><a href="' . $url . '">view in browser</a></p>' . "\n" . $body . "\n";
        $html = applyTheme($body);
        return $html;
    }

    private function getNotificationHtmlSubscribed($theme, $page, $pagePath, $altUrl)
    {
        $GLOBALS['pizza']['pagePath'] = $pagePath;
        $GLOBALS['pizza']['page'] = $page;
        $GLOBALS['pizza']['accessMode'] = 'html-email';
        $GLOBALS['pizza']['theme'] = $theme;
        $body = $this->tp->applyToppings($GLOBALS['pizza']['page']['body']);
        $body = $this->toHtmlEmail($GLOBALS['pizza']['pagePath'], $body, $altUrl);
        // Add 'read in browser' link.
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $siteName = strtolower($GLOBALS['pizza']['config']['siteName']);
        $url = $urlRoot . $GLOBALS['pizza']['pagePath'];
        if ($altUrl != 'none') $url = $altUrl;
        $body = '<p style="text-align: center;"><a href="' . $url . '">view in browser</a></p>' . "\n" . $body;
        // Add 'unsubscribe' link.
        $body = $body . '<p style="text-align: center;"><a href="' . $urlRoot . '/login?profile">unsubscribe</a></p>' . "\n";
        $html = applyTheme($body);
        return $html;
    }

    private function getNotificationText($pagePath, $altUrl)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $siteName = $GLOBALS['pizza']['config']['siteName'];
        $link = '    ' . $urlRoot . $pagePath . "\n";
        if ($altUrl != 'none') $link = $altUrl;
        ob_start();
        echo "$siteName\n";
        echo "=======================================================\n\n";
        echo "This is an automatically generated message.\n";
        echo "$siteName has been updated:\n\n";
        echo "$link";
        $message = ob_get_clean();
        return $message;
    }

    private function getNotificationTextSubscribed($pagePath, $altUrl)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $siteName = $GLOBALS['pizza']['config']['siteName'];
        $link = '    ' . $urlRoot . $pagePath . "\n\n";
        if ($altUrl != 'none') $link = $altUrl;
        ob_start();
        echo "$siteName\n";
        echo "=======================================================\n\n";
        echo "This is an automatically generated message.\n";
        echo "$siteName has been updated:\n\n";
        echo "$link";
        echo "To unsubscribe, log in and uncheck notifications:\n";
        echo 'https://' . strtolower($siteName) . "/login?profile\n";
        $message = ob_get_clean();
        return $message;
    }

    private function toHtmlEmail($pagePath, $body, $altUrl)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        // For SVG, use PNG equivalent if available, remove otherwise.
        $body = $this->convertSvgImages($body);
        // Convert <video> to <img> with link.
        $url = $urlRoot . $pagePath;
        if ($altUrl != 'none') $url = $altUrl;
        $body = preg_replace(
            '~<video\s+.*poster=[\'"]([^\'"]+)[\'"].*</video>~',
            '<a href="' . $url . '"><img src="$1"></a>',
            $body
        );
        // Make images with surrounding <a> element link to the article.
        $body = preg_replace('~(<a\s.*\s?href=[\'"])[^\'"]+([\'"].*><img\s[^>]+></a>)~U',
            "$1$url$2", $body);
        // Add <span><font> around anchor texts.
        // $body = preg_replace('~(<a\s+href[^>]+>)(.+)</a>~', "$1<span><font>$2</font></span></a>", $body);
        return $body;
    }

    private function convertSvgImages($html)
    {
        // Save the <img> elements that are SVGs and substitute with [SVG#] placeholders.
        $html = preg_replace_callback(
            '/(<img\s+.*\s?src=[\'"].+\.svg[\'"].*>)/U',
            function ($matches)
            {
                $this->svgImages[$this->i] = trim($matches[1]);
                $s = '[SVG' . $this->i . ']';
                $this->i++;
                return $s;
            },
            $html
        );
        // Check each SVG image to see of a PNG is available and use that instead.
        for ($i = 0; $i < count($this->svgImages); $i++)
        {
            preg_match('/src=[\'"](.+\.svg)[\'"]/', $this->svgImages[$i], $matches);
            $url = $matches[1];
            $parts = parse_url($url);
            $png = substr($parts['path'], 0, -4) . '.png';
            if ($this->cm->hasPage($png))
            {
                // Use PNG equivalent.
                $this->svgImages[$i] = preg_replace(
                    '/<img\s+.*\s?src=[\'"].+\K\.svg[\'"]/U',
                    '.png"',
                    $this->svgImages[$i]
                );
            }
            else
            {
                // Remove SVG img element.
                $this->svgImages[$i] = '';
            }
        }
        // Replace the "[SVG#]" placeholders with their respective
        // <img> pieces.
        $html = preg_replace_callback(
            '/\[SVG(\d+)\]/',
            function ($matches)
            {
                return $this->svgImages[$matches[1]];
            },
            $html
        );
        return $html;
    }
}

$d = new NotificationManager();
$d->doNotifications();
?>
