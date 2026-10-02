<?php
// Copyright 2023 Fleming Computer.
// All Rights Reserved.
?>
<?php
class Session
{
    public $data;
    public $timeout;

    private $cookieOptions;
    private $dataChanged;
    private $includes;
    private $mode;
    private $sessionId;
    private $sessionName;
    private $t;

    function __construct($sessionName)
    {
        $this->t = $GLOBALS['pizza']['t'];
        $this->cookieOptions = array(
            'expires' => 0,
            'path' => '/',
            'domain' => $this->getCookieDomain(),
            'samesite' => 'Lax'
        );
        $this->mode = '';
        $this->data = array();
        $this->includes = array();
        $this->dataChanged = false;
        $this->sessionId = '';
        $this->sessionName = $sessionName;
        $this->timeout = 3600 * 2;
    }

    function __destruct()
    {
        if ($this->mode != 'read-only')
        {
            if ($this->dataChanged)
            {
                $includes = $this->t->escapeString(serialize($this->includes));
                $data = $this->t->escapeString(serialize($this->data));
                $q = "
                    UPDATE sessions SET includes = '$includes', `data` = '$data'
                    WHERE sessionId = '$this->sessionId'
                ";
                $this->t->query($q, false);
            }
            $this->t->commit();
        }
    }

    function beginSession($mode = '')
    {
        $this->mode = $mode;
        // These are the same "do not cache" headers that PHP's built-in
        // session framework sends.
        header('Cache-Control: no-cache, no-store, must-revalidate, post-check=0, pre-check=0');
        header('Expires: Thu, 19 Nov 1981 08:52:00 GMT');
        header('Pragma: no-cache');
        if (!isset($_COOKIE[$this->sessionName]))
        {
            if ($_SERVER['REQUEST_METHOD'] == 'HEAD') return; // We can't 302 a HEAD request.
            $this->sessionId = $this->generateSessionId();
            $this->loadSession();
            return;
        }
        // Get proposed session ID from existing cookie and validate.
        $this->sessionId = $_COOKIE[$this->sessionName];
        if (!$this->sessionIsValid($this->sessionId))
            $this->sessionId = $this->generateSessionId(); // Start new session.
        $this->loadSession();
    }

    function hs($field)
    {
        return isset($this->data[$field]);
    }

    function resetSession()
    {
        $this->includes = array();
        $includes = serialize($this->includes);
        $this->data = array();
        $data = serialize($this->data);
        $q = "
            UPDATE sessions
            SET includes = '$includes', `data` = '$data', `timeStamp` = UTC_TIMESTAMP()
            WHERE sessionId = '$this->sessionId'
        ";
        $this->t->query($q, false);
    }

    function sc($field)
    {
        if (!isset($this->data[$field])) return;
        unset($this->includes[$field]);
        unset($this->data[$field]);
        $this->dataChanged = true;
    }

    function sessionExists()
    {
        if (!isset($_COOKIE[$this->sessionName])) return false;
        if (!$this->sessionIsValid($_COOKIE[$this->sessionName])) return false;
        return true;
    }

    function sg($field)
    {
        if (isset($this->data[$field])) return $this->data[$field];
        return false;
    }

    function sge($field, $default)
    {
        if (isset($this->data[$field])) return $this->data[$field];
        return $default;
    }

    function ss($field, $value)
    {
        if (gettype($value) == 'object') $this->includes[$field] = get_class($value);
        else if (isset($this->data[$field]) && ($this->data[$field] == $value)) return;
        $this->data[$field] = $value;
        $this->dataChanged = true;
    }

    private function generateSessionId()
    {
        // Delete expired sessions.
        $tzOffset = date('Z') / 3600;
        $q = "
            DELETE FROM sessions
            WHERE (UNIX_TIMESTAMP() - UNIX_TIMESTAMP(`timeStamp` + INTERVAL $tzOffset HOUR) > $this->timeout)
        ";
        $this->t->query($q, false);
        // Generate new session ID.
        $emptyArray = serialize(array());
        while (1)
        {
            $sid = '';
            for ($i = 1; $i <= 16; $i++)
                $sid .= str_pad(dechex(mt_rand(0, 255)), 2, '0', STR_PAD_LEFT);
            // Add salt.
            $i = hexdec($sid[1]);
            $d = $sid[$i * 2 + 1] . $sid[$i * 2];
            $sid .= $d;
            // Attempt to insert new session ID.
            $q = "
                INSERT INTO sessions (sessionId, `timeStamp`, includes, `data`)
                VALUES ('$sid', UTC_TIMESTAMP(), '$emptyArray', '$emptyArray')
            ";
            if ($this->t->query($q, false)) break;
        }
        // Attempt to set the cookie.
        setcookie($this->sessionName, $sid, $this->cookieOptions);
        return $sid;
    }

    private function getCookieDomain()
    {
        if (!isset($_SERVER['HTTP_HOST']) || ($_SERVER['HTTP_HOST'] == 'localhost')) return '';
        if (isset($GLOBALS['pizza']['config']['lanHosts'])
            && (in_array($_SERVER['HTTP_HOST'], $GLOBALS['pizza']['config']['lanHosts'])))
            return $_SERVER['HTTP_HOST'];
        $parts = explode('.', $_SERVER['HTTP_HOST']);
        if (count($parts) < 2) return '';
        return $parts[count($parts) - 2] . '.' . $parts[count($parts) - 1];
    }

    private function loadSession()
    {
        $forUpdate = '';
        if ($this->mode != 'read-only')
        {
            // Normally we use row locking to prevent two or more simultaneous
            // PHP processes from modifying the same session data. But when
            // loading a file, we use 'read-only' to skip row locking to allow
            // for multiple long-running PHP file-streaming processes, that I
            // think don't need to modify session data but do need to read it.
            //
            // Row locking takes place using "... FOR UPDATE" in SELECTs.
            $forUpdate = 'FOR UPDATE';
            $this->t->beginTransaction();
        }
        // Update the session's timestamp.
        $q = "
            UPDATE sessions SET `timeStamp` = UTC_TIMESTAMP()
            WHERE sessionId = '$this->sessionId'
        ";
        $this->t->query($q, false);
        // Load-and-lock this session's data.
        $q = "SELECT `includes`, `data` FROM sessions WHERE sessionId = '$this->sessionId' $forUpdate";
        $this->t->query($q, false);
        $r = $this->t->getNextRecord();
        $this->includes = unserialize(trim($r['includes']));
        foreach ($this->includes as $className) @include_once($className . '.php');
        $this->data = unserialize($r['data']);
        $this->dataChanged = false;
        // If the session is for a logged in user, populate GLOBAL user info.
        if (isset($this->data['user'])) $GLOBALS['pizza']['user'] = $this->data['user'];
    }

    private function sessionIsValid($sessionId)
    {
        // 1. Must be well-formed ID, with good salt.
        if ((strlen($sessionId) != 34) || preg_match('/[^0-9a-f]/i', $sessionId)) return false;
        $salt1 = substr($sessionId, hexdec($sessionId[1]) * 2, 2);
        $salt2 = $sessionId[33] . $sessionId[32];
        if ($salt1 != $salt2) return false;
        // 2. Must exist in DB.
        $tzOffset = date('Z') / 3600;
        $q = "
            SELECT includes, UNIX_TIMESTAMP() AS t2,
            UNIX_TIMESTAMP(`timeStamp` + INTERVAL $tzOffset HOUR) AS t1
            FROM sessions WHERE sessionId = '$sessionId'
        ";
        $this->t->query($q, false);
        if ($this->t->num_rows == 0) return false;
        // 3. Must not be expired.
        $r = $this->t->getNextRecord();
        if ($r['t2'] - $r['t1'] > $this->timeout) return false;
        return true;
    }
}
?>
