<?php
// Copyright 2016 Fleming Computer.
// All Rights Reserved.
?>
<?php
class DaemonManager
{
    private $dieMinutes;
    private $name;
    private $t;
    private $tick;
    private $updateMinutes;

    function __construct()
    {
        $this->t = $GLOBALS['pizza']['t'];
        $this->tick = 0;
    }

    function launch($name, $updateMinutes = 1, $dieMinutes = 5)
    {
        $this->name = $name;
        $this->dieMinutes = $dieMinutes;
        $this->updateMinutes = $updateMinutes;
        if ($this->isRunning()) return false;
        $this->update();
        return true;
    }

    function shutdown()
    {
        $name = $this->name;
        $q = "DELETE FROM daemons WHERE daemon = '$name'";
        $this->t->query($q);
    }

    function update()
    {
        $tick2 = time();
        if (($tick2 - $this->tick) < ($this->updateMinutes * 60)) return;
        $this->tick = $tick2;
        $now = gmdate('Y-m-d H:i:s', $this->tick);
        $name = $this->name;
        $q = "LOCK TABLES daemons WRITE";
        $this->t->query($q);
        $q = "DELETE FROM daemons WHERE daemon = '$name'";
        $this->t->query($q);
        $q = "INSERT INTO daemons (daemon, updated) VALUES ('$name', '$now')";
        $this->t->query($q);
        $q = "UNLOCK TABLES";
        $this->t->query($q);
    }

    private function isRunning()
    {
        $name = $this->name;
        $m = $this->dieMinutes;
        $q = "
            SELECT * FROM daemons WHERE daemon = '$name'
            AND updated >= UTC_TIMESTAMP() - INTERVAL $m MINUTE
        ";
        $this->t->query($q);
        if ($this->t->num_rows > 0) return true;
        return false;
    }
}
?>
