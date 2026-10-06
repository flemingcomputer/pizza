<?php
// Copyright 2022 Fleming Computer.
// All Rights Reserved.
?>
<?php
class VersionManager
{
    private $t;

    // The versions are just strings, max length 10 characters. There is no
    // numerical order implied in the naming. The only version "order" is the
    // order of the array from start to end.
    // The update functions' names replace '.' with '_' in the version string
    // and are prefixed by "update_". For example, version '0.42' will look
    // for and call (if present) the update function "update_0_42()".
    // If an update function returns true, the version number will be updated.

    private $versions = array(
        '0.92', '0.10', '0.11', '0.12', '0.13'
    );

    private function update_0_13()
    {
        // Rename table `pizzaVersion` to `versions` and add Sandbox version.
        $q = "RENAME TABLE `pizzaVersion` TO `versions`";
        $this->t->query($q);
        $q = "ALTER TABLE `versions` RENAME COLUMN `versionString` TO `pizza`";
        $this->t->query($q);
        $q = "ALTER TABLE `versions` ADD COLUMN `sandbox` VARCHAR(10) NULL DEFAULT NULL";
        $this->t->query($q);
        $q = "UPDATE versions SET sandbox = 'abcdefg'";
        $this->t->query($q);
        return true;
    }

    private function update_0_11()
    {
        // Rename table `media` to `files`, etc.
        $q = "RENAME TABLE `media` TO `files`";
        $this->t->query($q);
        $q = "RENAME TABLE `mediaTouch` TO `filesTouch`";
        $this->t->query($q);
        $q = "ALTER TABLE `filesTouch` RENAME COLUMN `mediaId` TO `fileId`";
        $this->t->query($q);
        return true;
    }

    private function update_0_92()
    {
        // Rename media field parentId to pageId.
        $q = "
            ALTER TABLE `media` CHANGE `parentId` `pageId` BIGINT(20) UNSIGNED NULL DEFAULT NULL
        ";
        $this->t->query($q);
        return true;
    }

    function __construct()
    {
        $this->t = $GLOBALS['pizza']['t'];
    }

    function checkVersion()
    {
        $q = "SELECT pizza FROM versions";
        $result = $this->t->query($q, false);
        if ($result !== false)
        {
            $r = $this->t->getNextRecord();
            $version = $r['pizza'];
        }
        else
        {
            $q = "SELECT versionString FROM pizzaVersion";
            $this->t->query($q);
            $r = $this->t->getNextRecord();
            $version = $r['versionString'];
        }
        if ($this->versions[count($this->versions) - 1] === $version) return;
        $i = array_search($version, $this->versions);
        if ($i === false) return;
        $n = count($this->versions);
        for ($j = $i + 1; $j < $n; $j++)
        {
            $updateFunction = 'update_' . str_replace('.', '_', $this->versions[$j]);
            $result = true;
            if (method_exists($this, $updateFunction))
                $result = $this->$updateFunction();
            if ($result === true)
                $this->setVersion($this->versions[$j]);
        }
    }

    private function setVersion($versionString)
    {
        $vs = $this->t->escapeString($versionString);
        $q = "UPDATE versions SET pizza = '$vs'";
        $result = $this->t->query($q, false);
        if ($result === false)
        {
            $q = "UPDATE pizzaVersion SET versionString = '$vs'";
            $this->t->query($q);
        }
    }
}
?>
