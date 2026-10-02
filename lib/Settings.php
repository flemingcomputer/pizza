<?php
// Copyright 2016 Fleming Computer.
// All Rights Reserved.
?>
<?php
class Settings
{
    private $globalSettings;
    private $pageSettings;
    private $t;

    function __construct()
    {
        $this->pageSettings = array();
        $this->t = $GLOBALS['pizza']['t'];
    }

    function get($pagePath, $key, $default = false)
    {
        $pageId = $GLOBALS['pizza']['cm']->pagePathToPageId($pagePath);
        if ($pageId === false) return $default;
        if (!isset($this->pageSettings[$pageId]))
            $this->pageSettings[$pageId] = $this->loadPageSettings($pageId);
        if (!isset($this->pageSettings[$pageId][$key])) return $default;
        return $this->pageSettings[$pageId][$key];
    }

    function getGlobal($key, $default = false)
    {
        if (!isset($this->globalSettings))
            $this->globalSettings = $this->loadGlobalSettings();
        if (!isset($this->globalSettings[$key])) return $default;
        return $this->globalSettings[$key];
    }

    function set($pagePath, $key, $value)
    {
        // No update if:  not set and setting to ''
        // No update if:  set at 'foo' and setting to 'foo'
        $pageId = $GLOBALS['pizza']['cm']->pagePathToPageId($pagePath);
        if ($pageId === false) return;
        if (!isset($this->pageSettings[$pageId]))
            $this->pageSettings[$pageId] = $this->loadPageSettings($pageId);
        if (
            (!isset($this->pageSettings[$pageId][$key]) && ($value == ''))
            || (isset($this->pageSettings[$pageId][$key]) && ($value == $this->pageSettings[$pageId][$key]))
        )
            return;
        // It is being altered.
        if ($value != '') $this->pageSettings[$pageId][$key] = $value;
        else unset($this->pageSettings[$pageId][$key]);
        $mSettings = $this->t->escapeString(serialize($this->pageSettings[$pageId]));
        if ($mSettings == 'a:0:{}') $mSettings = '';
        $q = "
            UPDATE pages SET settings = '$mSettings'
            WHERE id = $pageId
            ORDER BY revision DESC
            LIMIT 1
        ";
        $this->t->query($q);
    }

    function setGlobal($key, $value)
    {
        // No update if:  not set and setting to ''
        // No update if:  set at 'foo' and setting to 'foo'
        if (!isset($this->globalSettings))
            $this->globalSettings = $this->loadGlobalSettings();
        if (
            (!isset($this->globalSettings[$key]) && ($value == ''))
            || (isset($this->globalSettings[$key]) && ($value == $this->globalSettings[$key]))
        )
            return;
        // It is being altered.
        if ($value != '') $this->globalSettings[$key] = $value;
        else unset($this->globalSettings[$key]);
        $mSettings = $this->t->escapeString(serialize($this->globalSettings));
        if ($mSettings == 'a:0:{}') $mSettings = '';
        $q = "UPDATE globalSettings SET settings = '$mSettings'";
        $this->t->query($q);
    }

    private function loadGlobalSettings()
    {
        $q = "SELECT settings FROM globalSettings";
        $this->t->query($q);
        $settings = array();
        if ($this->t->num_rows > 0)
        {
            $r = $this->t->getNextRecord();
            if ($r['settings'] != '')
                $settings = unserialize($r['settings']);
        }
        else
        {
            // Initialize the global settings with its one row.
            $q = "INSERT INTO globalSettings VALUES ('')";
            $this->t->query($q);
        }
        return $settings;
    }

    private function loadPageSettings($pageId)
    {
        $q = "SELECT settings FROM pages WHERE id = $pageId";
        $altRevision = gge('r', 'none');
        if (is_numeric($altRevision) && (intval($altRevision) == $altRevision))
            $q .= " AND revision <= $altRevision";
        $q .= " ORDER BY revision DESC LIMIT 1";
        $this->t->query($q);
        $settings = array();
        if ($this->t->num_rows > 0)
        {
            $r = $this->t->getNextRecord();
            if ($r['settings'] != '') $settings = unserialize($r['settings']);
        }
        return $settings;
    }
}
?>
