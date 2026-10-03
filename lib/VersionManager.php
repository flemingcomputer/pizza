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

    private $versions = array(
        '0.92', '0.10', '0.11', '0.12'
    );

    private function update_0_11()
    {
        // Rename table `media` to `files`, etc.
        try {
            $this->t->beginTransaction();
            $q = "RENAME TABLE `media` TO `files`";
            $this->t->query($q);
            $q = "RENAME TABLE `mediaTouch` TO `filesTouch`";
            $this->t->query($q);
            $q = "ALTER TABLE `filesTouch` RENAME COLUMN `mediaId` TO `fileId`";
            $this->t->query($q);
            $this->t->commit();
            return true;
        } catch (Exception $e) {
            $this->rollBack();
            return false;
        }
    }

    private function update_0_92()
    {
        // Rename media field parentId to pageId.
        try {
            $q = "
                ALTER TABLE `media` CHANGE `parentId` `pageId` BIGINT(20) UNSIGNED NULL DEFAULT NULL
            ";
            $this->t->query($q);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    function __construct()
    {
        $this->t = $GLOBALS['pizza']['t'];
    }

    function checkVersion()
    {
        $q = "SELECT versionString FROM pizzaVersion";
        $this->t->query($q);
        $r = $this->t->getNextRecord();
        $version = $r['versionString'];
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
        $q = "UPDATE pizzaVersion SET versionString = '$vs'";
        $this->t->query($q);
    }
}
?>
