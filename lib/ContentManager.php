<?php
// Copyright 2026 Fleming Computer.
// All Rights Reserved.
?>
<?php
class ContentManager
{
    private $breadcrumbs;
    private $cropBox;
    private $pagePaths;
    private $storageRoot;
    private $t;

    function __construct()
    {
        $this->breadcrumbs = array();
        $this->cropBox = $GLOBALS['pizza']['config']['cropBox'] ?? 0;
        $this->pagePaths = array();
        $this->storageRoot = storageRoot();
        $this->t = $GLOBALS['pizza']['t'];
    }

    function addFile($pagePath, $fileName, $fileSize, $mimeType, $contents)
    {
        if ($this->hasFile($pagePath, $fileName)) return false;
        $dimensions = '';
        // If this is a supported (non-SVG) image type, constrain the image.
        if (
            ($mimeType == 'image/jpeg')
            || ($mimeType == 'image/pjpeg')
            || ($mimeType == 'image/gif')
            || ($mimeType == 'image/png')
            || ($mimeType == 'image/webp'))
        {
            if ($this->cropBox > 0)
            {
                // Constrain the result.
                list($contents, $x, $y) = $this->imageConstrain($mimeType, $contents, $this->cropBox);
                $dimensions = $x . 'x' . $y;
                $fileSize = strlen($contents);
            }
            else
            {
                // Store image as-is, no resizing.
                $im = new Imagick();
                $im->readImageBlob($contents);
                $x = $im->getImageWidth();
                $y = $im->getImageHeight();
                $im->clear();
                $dimensions = $x . 'x' . $y;
            }
        }
        if ($this->storageRoot !== false)
        {
            $mContents = '';
            $this->storageSave($pagePath, $fileName, $contents);
        }
        else
            $mContents = $this->t->escapeString($contents);
        unset($contents);
        $q = "LOCK TABLES pages WRITE, `files` WRITE";
        $this->t->query($q);
        $q = "SELECT MAX(id) AS maxId FROM `files`";
        $this->t->query($q);
        $r = $this->t->getNextRecord();
        $newId = ($r['maxId'] ?? 0) + 1;
        $newRevision = $this->getMaxRevision() + 1;
        // Get number of caching columns for inserting initial values.
        $q = "SHOW COLUMNS FROM `files` LIKE 'cache%Dimensions'";
        $this->t->query($q);
        $numCaches = $this->t->num_rows;
        $cacheFields = '';
        $cacheValues = '';
        for ($i = 1; $i <= $numCaches; $i++)
        {
            $cacheFields .= ", cache{$i}, cache{$i}Dimensions, cache{$i}Read";
            $cacheValues .= ", '', '', '1970-01-01 00:00:00'";
        }
        $mFileName = $this->t->escapeString($fileName);
        $mMimeType = $this->t->escapeString($mimeType);
        $pageId = $this->pagePathToPageId($pagePath);
        $q = "
            INSERT INTO `files`
            (id, pageId, revision, fileName, fileSize, mimeType, views, `text`, contents, dimensions$cacheFields)
            VALUES
            ($newId, $pageId, $newRevision, '$mFileName', $fileSize, '$mMimeType', 0, '', '$mContents', '$dimensions'$cacheValues)
        ";
        $this->t->query($q);
        $q = "UNLOCK TABLES";
        $this->t->query($q);
        $r['size'] = $fileSize;
        if ($dimensions != '')
        {
            $r['width'] = $x;
            $r['height'] = $y;
        }
        return $r;
    }

    function addPage($pagePath, $pageName, $body = '', $kind = 'page',
        $overrideReserved = false, $dateTime = false)
    {
        // Do not add below a reserved page unless overridden.
        if (($overrideReserved != 'override') && $this->isReservedPage($pagePath)) return false;
        $breadcrumbs = $this->getBreadcrumbs($pagePath);
        if ($breadcrumbs === false) return false;
        $breadcrumbs = $breadcrumbs['breadcrumbs'];
        $pageUri = $this->pageNameToPageUri($pageName);
        // If pageUri starts and ends with '-', e.g. '-hello-', silently refuse.
        if (preg_match('/^-.*-$/', $pageUri)) return false;
        // Do not add a reserved page.
        if ($this->isReservedPage($pagePath . $pageUri)) return false;
        // If page to be created is an alias, delete the alias.
        $breadcrumbs2 = $this->getBreadcrumbs($pagePath . $pageUri);
        if (isset($breadcrumbs2['isAlias']))
        {
            $breadcrumbs2 = $breadcrumbs2['breadcrumbs'];
            $n = count($breadcrumbs2);
            $pageId = $breadcrumbs2[$n - 1]['id'];
            $mPageUri = $this->t->escapeString($breadcrumbs2[$n - 1]['pageUri']);
            $q = "
                DELETE FROM pageAliases
                WHERE pageId = $pageId AND pageUri = '$mPageUri'
            ";
            $this->t->query($q);

        }
        $q = "LOCK TABLES pages WRITE, `files` WRITE";
        $this->t->query($q);
        $q = "SELECT MAX(id) AS maxId FROM pages";
        $this->t->query($q);
        $r = $this->t->getNextRecord();
        $newPageId = $r['maxId'] + 1;
        $parent = $breadcrumbs[count($breadcrumbs) - 1];
        $parentId = $parent['id'];
        $newMode = substr($parent['mode'], 0, 1) . 'y' . substr($parent['mode'], 2);
        $parentGroupId = $parent['groupId'];
        $newRevision = $this->getMaxRevision() + 1;
        $mPageName = $this->t->escapeString($pageName);
        $mPageUri = $this->t->escapeString($pageUri);
        if ($body == '')
            $body = $kind == 'page' ? $this->generateInitialBody($pageName) : '';
        $mBody = $this->t->escapeString($body);
        $userId = $GLOBALS['pizza']['user']['id'];
        $name = $GLOBALS['pizza']['user']['firstName'] . ' ' . $GLOBALS['pizza']['user']['lastName'];
        $mName = $this->t->escapeString($name);
        if ($dateTime === false)
            $created = gmdate('Y-m-d H:i:s');  // now
        else
            $created = gmdate('Y-m-d H:i:s', strtotime($dateTime));  // then
        $q = "
            INSERT INTO pages (
                id, parentId, revision, kind, pageName, pageUri, body
                , mode, userId, groupId, `name`, created, modified
                , views, notify, settings, lockedBy, lockedOn
            )
            VALUES (
                $newPageId, $parentId, $newRevision, '$kind', '$mPageName', '$mPageUri', '$mBody'
                , '$newMode', $userId, $parentGroupId, '$mName', '$created', '$created'
                , 0, '1970-01-01 00:00:00', '', 0, '1970-01-01 00:00:00'
            )
        ";
        $result = $this->t->query($q);
        $q = "UNLOCK TABLES";
        $this->t->query($q);
        // Reset the breadcrumbs cache.
        $this->breadcrumbs = array();
        if (!$result) return false;
        return true;
    }

    function addTempPage($pagePath, $kind = 'page', $inSitemap = 'n')
    {
        // Do not add below a reserved page.
        if ($this->isReservedPage($pagePath)) return false;
        $breadcrumbs = $this->getBreadcrumbs($pagePath);
        if ($breadcrumbs === false) return false;
        $breadcrumbs = $breadcrumbs['breadcrumbs'];
        while (true)
        {
            $pageUri = 'temp-' . mt_rand(11111111, 99999999);
            if (!$this->hasPage($pagePath . $pageUri)) break;
        }
        $q = "LOCK TABLES pages WRITE, `files` WRITE";
        $this->t->query($q);
        // Delete abandoned temp pages.
        $q = "
            DELETE FROM pages
            WHERE pageUri REGEXP 'temp-[[:digit:]]{8}'
            AND (UTC_TIMESTAMP() - INTERVAL 24 HOUR > created)
        ";
        $this->t->query($q);
        $q = "SELECT MAX(id) AS maxId FROM pages";
        $this->t->query($q);
        $r = $this->t->getNextRecord();
        $newPageId = $r['maxId'] + 1;
        $parentId = $breadcrumbs[count($breadcrumbs) - 1]['id'];
        $newRevision = $this->getMaxRevision() + 1;
        $newMode = 'y' . $inSitemap . 'rw----';
        $mPageUri = $this->t->escapeString($pageUri);
        $userId = $GLOBALS['pizza']['user']['id'];
        $name = $GLOBALS['pizza']['user']['firstName'] . ' ' . $GLOBALS['pizza']['user']['lastName'];
        $mName = $this->t->escapeString($name);
        $now = gmdate('Y-m-d H:i:s');
        $q = "
            INSERT INTO pages (
                id, parentId, revision, kind, pageName, pageUri, body
                , mode, userId, groupId, `name`, created, modified
                , views, notify, settings, lockedBy, lockedOn
            )
            VALUES (
                $newPageId, $parentId, $newRevision, '$kind', '$mPageUri', '$mPageUri', ''
                , '$newMode', $userId, 2, '$mName', '$now', '$now'
                , 0, '1970-01-01 00:00:00', '', 0, '1970-01-01 00:00:00'
            )
        ";
        $result = $this->t->query($q);
        $q = "UNLOCK TABLES";
        $this->t->query($q);
        // Reset the breadcrumbs cache.
        $this->breadcrumbs = array();
        if (!$result) return false;
        return $pagePath . $pageUri;
    }

    function changePermissions($pagePath, $mode, $userId = false, $groupId = false, $recurse = false)
    {
        if ($this->isReservedPage($pagePath)) return false;
        $topId = $this->pagePathToPageId($pagePath);
        if ($topId === false) return false;
        $pageIds = $topId;
        if ($recurse === true)
        {
            // Construct comma-separated list of page IDs.
            $sitemap = $this->getSitemap($pagePath);
            if ($sitemap === false) return false;
            $pages = $this->pageTreeToPageList($sitemap);
            $pageIds = '';
            foreach ($pages as $k => $p) $pageIds .= $k . ',';
            $pageIds = substr($pageIds, 0, -1);
        }
        $mMode = $this->t->escapeString($mode);
        $setQ = "mode = '$mMode'";
        if ($userId !== false)
        {
            $user = $GLOBALS['pizza']['um']->getUser($userId);
            $mName = $this->t->escapeString($user['firstName'] . ' ' . $user['lastName']);
            $setQ .= ", userId = $userId, `name` = '$mName'";
        }
        if ($groupId !== false) $setQ .= ", groupId = $groupId";
        $q = "UPDATE pages SET $setQ WHERE id IN ($pageIds)";
        $this->t->query($q);
    }

    function copyTree($srcPagePath, $dstParentPath, $newName = '')
    {
        // This creates a copy of all content associated with this pagePath,
        // including subpages and related files.
        // Do not copy the top page.
        if ($srcPagePath == '/') return false;
        // Do not copy to a reserved page.
        if ($this->isReservedPage($dstParentPath)) return false;
        // If proposed newName under dstParentPath exists, bail.
        if ($newName != '')
        {
            $newUri = $this->pageNameToPageUri($newName);
            $newPagePath = $dstParentPath . $newUri . '/';
            if ($this->hasPage($newPagePath)) return false;
        }
        $breadcrumbs = $this->getBreadcrumbs($srcPagePath);
        if ($breadcrumbs === false) return false;
        $breadcrumbs = $breadcrumbs['breadcrumbs'];
        $srcPageId = $breadcrumbs[count($breadcrumbs) - 1]['id'];
        $srcPageUri = $breadcrumbs[count($breadcrumbs) - 1]['pageUri'];
        $breadcrumbs = $this->getBreadcrumbs($dstParentPath);
        if ($breadcrumbs === false) return false;
        $breadcrumbs = $breadcrumbs['breadcrumbs'];
        $dstParentId = $breadcrumbs[count($breadcrumbs) - 1]['id'];
        // Fetch the entire branch sorted by depth (parents always come before children)
        $q = "
            WITH RECURSIVE treeBranch AS (
                SELECT *, 0 AS depth
                FROM pages
                WHERE id = $srcPageId
                UNION ALL
                SELECT c.*, tb.depth + 1
                FROM pages AS c
                JOIN treeBranch tb ON c.parentId = tb.id
            )
            SELECT * FROM treeBranch ORDER BY depth ASC;
        ";
        $this->t->query($q);
        $rows = $this->t->getAllRecords();
        if (empty($rows)) return false;
        // Map old IDs to their newly generated IDs.
        // Seed it with the original root's parent pointing to the new destination parent.
        $rootParentId = $rows[0]['parentId'];
        $idMap = array(
            $rootParentId => $dstParentId
        );
        // Prepare the reusable insert statement.
        $insertStmt = $this->t->prepare("
            INSERT INTO pages (id, parentId, revision, kind, pageName, pageUri, body,
                mode, userId, groupId, name, created, modified, views, notify, settings,
                lockedBy, lockedOn)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        // Bind references to variables that we'll change inside the loop.
        $newId = null;
        $newParentId = null;
        $revision = null;
        $kind = null;
        $pageName = null;
        $pageUri = null;
        $body = null;
        $mode = null;
        $userId = null;
        $groupId = null;
        $name = null;
        $created = null;
        $modified = null;
        $views = null;
        $notify = null;
        $settings = null;
        $lockedBy = null;
        $lockedOn = null;
        $insertStmt->bind_param('iiisssssiisssissis', $newId, $newParentId, $revision, $kind,
            $pageName, $pageUri, $body, $mode, $userId, $groupId, $name, $created,
            $modified, $views, $notify, $settings, $lockedBy, $lockedOn);
        // Get next insert id.
        $q = "SELECT MAX(id) AS maxId FROM pages";
        $this->t->query($q);
        $r = $this->t->getNextRecord();
        $newInsertId = $r['maxId'] + 1;
        // Loop through and duplicate nodes top-down.
        foreach ($rows as $i => $row)
        {
            $id = $row['id'];
            $parentId = $row['parentId'];
            $revision = $row['revision'];
            $kind = $row['kind'];
            $pageName = $row['pageName'];
            $pageUri = $row['pageUri'];
            if ($i == 0)
            {
                if ($newName != '')
                {
                    $pageName = $newName;
                    $pageUri = $newUri;
                }
                else if ($parentId == $dstParentId)
                {
                    $pageName .= '_Copy';
                    $pageUri .= '-copy';
                }
            }
            $body = $row['body'];
            $mode = $row['mode'];
            $userId = $row['userId'];
            $groupId = $row['groupId'];
            $name = $row['name'];
            $created = $row['created'];
            $modified = $row['modified'];
            $views = $row['views'];
            $notify = $row['notify'];
            $settings = $row['settings'];
            $lockedBy = $row['lockedBy'];
            $lockedOn = $row['lockedOn'];
            // Bind new ID and parent ID.
            $newId = $newInsertId;
            $newParentId = $idMap[$parentId];
            // Execute the insert
            $insertStmt->execute();
            // Map old ID to new ID.
            $idMap[$id] = $newId;
            // Increment the ID.
            $newInsertId++;
        }
        // Finish up.
        $insertStmt->close();
        // Copy associated files.
        // Skipping the first entry parent-of-tree, copy each old page's
        // files to the corresponding new page.
        $idMap = array_slice($idMap, 1, null, true);
        foreach ($idMap as $oldId => $newId)
        {
            $srcPath = $this->pageIdToPagePath($oldId);
            $dstPath = $this->pageIdToPagePath($newId);
            $files = $this->getFileInfo($srcPath);
            if ($files === false) continue;
            foreach ($files as $f)
            {
                $fileName = $f['fileName'];
                $fileSize = $f['fileSize'];
                $mimeType = $f['mimeType'];
                $revision = $f['revision'];
                $contents = $this->getFileContents($srcPath, $fileName, $revision);
                $this->addFile($dstPath, $fileName, $fileSize, $mimeType, $contents);
            }
        }
        return true;
    }

    function deleteFile($pagePath, $fileName, $revisable = false)
    {
        $pageId = $this->pagePathToPageId($pagePath);
        if ($pageId === false) return false;
        $mFileName = $this->t->escapeString($fileName);
        if ($revisable === true)
        {
            // When we delete a file, we don't actually delete it.  Instead, we
            // create a new revision that is the mimeType 'deleted', and
            // whose content is essentially empty.
            $q = "LOCK TABLES pages WRITE, `files` WRITE";
            $this->t->query($q);
            $newRevision = $this->getMaxRevision() + 1;
            $q = "
                SELECT id FROM (
                    SELECT id, mimeType FROM `files`
                    WHERE pageId = $pageId AND fileName = '$mFileName'
                    ORDER BY revision DESC
                    LIMIT 1
                ) AS files2 WHERE mimeType != 'deleted'
            ";
            $this->t->query($q);
            if ($this->t->num_rows == 0) return false;
            $r = $this->t->getNextRecord();
            $id = $r['id'];
            // Clear cache for this file.
            $q = "SHOW COLUMNS FROM `files` LIKE 'cache%Dimensions'";
            $this->t->query($q);
            $numCaches = $this->t->num_rows;
            if ($numCaches == 0) return false;
            $cacheFields = '';
            $cacheValues = '';
            $cacheUpdate = '';
            for ($i = 1; $i <= $numCaches; $i++)
            {
                $cacheFields .= ", cache{$i}, cache{$i}Dimensions, cache{$i}Read";
                $cacheValues .= ", '', '', '1970-01-01 00:00:00'";
                $cacheUpdate .= "cache{$i}='',cache{$i}Dimensions='',cache{$i}Read='1970-01-01 00:00:00',";
            }
            $cacheUpdate = substr($cacheUpdate, 0, -1);
            $q = "
                UPDATE `files` SET $cacheUpdate
                WHERE pageId = $pageId AND fileName = '$mFileName'
                ORDER BY revision DESC
                LIMIT 1
            ";
            $this->t->query($q);
            // Insert the 'deleted' revision.
            $q = "
                INSERT INTO `files`
                (id, pageId, revision, fileName, fileSize, mimeType, views, `text`, contents, dimensions$cacheFields)
                VALUES
                ($id, $pageId, $newRevision, '$mFileName', 0, 'deleted', 0, '', '', ''$cacheValues)
            ";
            $this->t->query($q);
            $q = "UNLOCK TABLES";
            $this->t->query($q);
        }
        else
        {
            // Delete the file outright.
            // Delete from db.
            $q = "
                DELETE FROM `files`
                WHERE pageId = $pageId AND fileName = '$mFileName'
            ";
            $this->t->query($q);
            if ($this->storageRoot !== false)
            {
                // Delete from storage.
                $this->storageDelete($pagePath, $fileName);
            }
        }
    }

    function deletePage($pagePath, $revisable = false)
    {
        // Do not delete the top page.
        if ($pagePath == '/') return false;
        // Do not delete a reserved page.
        if ($this->isReservedPage($pagePath)) return false;
        $breadcrumbs = $this->getBreadcrumbs($pagePath);
        if ($breadcrumbs === false) return false;
        $breadcrumbs = $breadcrumbs['breadcrumbs'];
        $n = count($breadcrumbs);
        $pageId = $breadcrumbs[$n - 1]['id'];
        $parentId = $breadcrumbs[$n - 2]['id'];
        $pageName = $breadcrumbs[$n - 1]['pageName'];
        $pageUri = $breadcrumbs[$n - 1]['pageUri'];
        // If pageUri starts and ends with '-', e.g. '-hello-', silently refuse.
        if (preg_match('/^-.*-$/', $pageUri)) return false;
        $newMode = 'y' . substr($breadcrumbs[$n - 1]['mode'], 1, 1) . 'rwr-r-';
        $groupId = $breadcrumbs[$n - 1]['groupId'];
        // Do not delete a page that has children.
        if ($this->pageHasChildren($pagePath)) return false;
        // Uncache this breadcrumb trail since we are deleting it.
        unset($this->breadcrumbs[$pagePath]);
        // Delete outright if temp page or if not revisable.
        if ($this->isTempPage($pagePath) || ($revisable === false))
        {
            // Delete its files.
            $q = "DELETE FROM `files` WHERE pageId = $pageId";
            $this->t->query($q);
            // Delete aliases of this page.
            $q = "DELETE FROM pageAliases WHERE pageId = $pageId";
            $this->t->query($q);
            // Delete the page itself.
            $q = "DELETE FROM pages WHERE id = $pageId";
            $this->t->query($q);
            // Delete storage-based files.
            $this->storageDeleteTree($pagePath);
            return true;
        }
        // When we delete a page, we don't actually delete it.  Instead, we
        // create a new revision that is the kind 'deleted' with no body.
        $q = "LOCK TABLES pages WRITE, `files` WRITE";
        $this->t->query($q);
        $newRevision = $this->getMaxRevision() + 1;
        $mPageName = $this->t->escapeString($pageName);
        $mPageUri = $this->t->escapeString($pageUri);
        $userId = $GLOBALS['pizza']['user']['id'];
        $name = $GLOBALS['pizza']['user']['firstName'] . ' ' . $GLOBALS['pizza']['user']['lastName'];
        $mName = $this->t->escapeString($name);
        $now = gmdate('Y-m-d H:i:s');
        $q = "
            INSERT INTO pages (
                id, parentId, revision, kind, pageName, pageUri, body
                , mode, userId, groupId, `name`, created, modified
                , views, notify, settings, lockedBy, lockedOn
            )
            VALUES (
                $pageId, $parentId, $newRevision, 'deleted', '$mPageName', '$mPageUri', ''
                , '$newMode', $userId, $groupId, '$mName', '$now', '$now'
                , 0, '1970-01-01 00:00:00', '', 0, '1970-01-01 00:00:00'
            )
        ";
        $this->t->query($q);
        $q = "UNLOCK TABLES";
        $this->t->query($q);
        // Delete aliases of this page.
        $q = "DELETE FROM pageAliases WHERE pageId = $pageId";
        $this->t->query($q);
        return true;
    }

    function deleteRevisions($mode = '')
    {
        // Mode must be one of:
        // 'all' - all but most recent revisions purged, and reset to r=1.
        // 'rN', where N is a revision # - revisions < N are purged.
        // 'YYYY-MM-DD' - revisions older than this are purged.
        // 'YYYY-MM-DD HH:MM:SS' - revisions older than this are purged.
        $allMode = $mode == 'all';
        $rMode = preg_match('/^r\d+$/', $mode);
        $dateMode = preg_match('/^\d{4}-\d{2}-\d{2}$/', $mode);
        $dateTimeMode = preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $mode);
        if (!$allMode && !$rMode && !$dateMode && !$dateTimeMode) return false;
        if ($allMode) $this->deleteAllRevisions();
        else if ($rMode) $this->deleteLowerRevisions(substr($mode, 1));
        else if ($dateMode) $this->deleteOlderRevisions("$mode 00:00:00");
        else if ($dateTimeMode) $this->deleteOlderRevisions($mode);
        return true;
    }

    function deleteTree($pagePath)
    {
        // This permanently deletes all content associated with this pagePath,
        // including subpages, page aliases, and related files.
        // Do not delete the top page.
        if ($pagePath == '/') return false;
        // Do not delete a reserved page.
        if ($this->isReservedPage($pagePath)) return false;
        $breadcrumbs = $this->getBreadcrumbs($pagePath);
        if ($breadcrumbs === false) return false;
        // Uncache this breadcrumb trail since we are deleting it.
        unset($this->breadcrumbs[$pagePath]);
        $breadcrumbs = $breadcrumbs['breadcrumbs'];
        $n = count($breadcrumbs);
        $pageId = $breadcrumbs[$n - 1]['id'];
        // Walk the tree to collect the IDs of all subpages to delete.  Start
        // by seeding the walk with pageId.
        $thisWalk = array($pageId);
        $deletePages = array_merge(array(), $thisWalk);
        while (!empty($thisWalk))
        {
            $children = array();
            foreach ($thisWalk as $id)
            {
                $q = "SELECT id FROM pages WHERE parentId = $id";
                $this->t->query($q);
                $n = $this->t->num_rows;
                for ($i = 1; $i <= $n; $i++)
                {
                    $r = $this->t->getNextRecord();
                    $children[] = $r['id'];
                }
            }
            $deletePages = array_merge($deletePages, $children);
            $thisWalk = $children;
        }
        // For each page ID, delete its page aliases, files, and lastly the
        // page itself.  This will delete all revisions as well.
        $q = "LOCK TABLES pageAliases WRITE, `files` WRITE, pages WRITE";
        $this->t->query($q);
        foreach ($deletePages as $id)
        {
            // Delete its page aliases.
            $q = "
                DELETE pageAliases FROM pages
                JOIN pageAliases ON pages.id = pageAliases.pageId
                AND pages.parentId = pageAliases.parentId
                WHERE pages.id = $id
            ";
            $this->t->query($q);
            // Delete its files.
            $q = "DELETE FROM `files` WHERE pageId = $id";
            $this->t->query($q);
            // Delete the page itself.
            $q = "DELETE FROM pages WHERE id = $id";
            $this->t->query($q);
        }
        $q = "UNLOCK TABLES";
        $this->t->query($q);
        // Delete any files in storage.
        $this->storageDeleteTree($pagePath);
    }

    function dumpFile($pagePath, $fileName)
    {
        $fileInfo = $this->getFileInfo($pagePath, $fileName);
        if ($fileInfo === false) return false;
        $mimeType = $fileInfo['mimeType'];
        if (
            ($mimeType == 'image/jpeg')
            || ($mimeType == 'image/pjpeg')
            || ($mimeType == 'image/gif')
            || ($mimeType == 'image/png')
            || ($mimeType == 'image/webp'))
        {
            // Treat this as an image request.
            $width = 99999;
            $height = 99999;
            if (hg('width') && is_numeric(gg('width'))) $width = gg('width');
            if (hg('height') && is_numeric(gg('height'))) $height = gg('height');
            // Constrain within the bounding box given by width and height.
            $dims = $this->imageGetDimensions($pagePath, $fileName, $width, $height);
            if ($dims === false) return false;
            list($x, $y) = $dims;
            if (abs($x - $width) + abs($y - $height) == 1)
            {
                // The contrained result only differs by one pixel on one axis, so
                // take the width and height values as-are.  This prevents one-pixel
                // difference between cache and browser-specified dimensions.
                $x = $width;
                $y = $height;
            }
            // This will dump and exit if successful.
            $this->dumpImage($pagePath, $fileName, $x, $y);
        }
        else // Treat this as generic file content.
        {
            // This will dump and exit if successful.
            $this->dumpContents($pagePath, $fileName);
        }
        // Only here if dump goes wrong.
        return false;
    }

    function editPage($pagePath, $body, $revisable = false, $createdDate = false)
    {
        if ($this->isReservedPage($pagePath)) return false;
        $breadcrumbs = $this->getBreadcrumbs($pagePath);
        if ($breadcrumbs === false) return false;
        $breadcrumbs = $breadcrumbs['breadcrumbs'];
        $n = count($breadcrumbs);
        $pageId = $breadcrumbs[$n - 1]['id'];
        $parentId = 0;
        if ($n > 1) $parentId = $breadcrumbs[$n - 2]['id'];
        $revision = $breadcrumbs[$n - 1]['revision'];
        $kind = $breadcrumbs[$n - 1]['kind'];
        $pageName = $breadcrumbs[$n - 1]['pageName'];
        $pageUri = $breadcrumbs[$n - 1]['pageUri'];
        $mode = $breadcrumbs[$n - 1]['mode'];
        $groupId = $breadcrumbs[$n - 1]['groupId'];
        // Add ending newline if none exists.
        if (substr($body, -1, 1) != "\n") $body .= "\n";
        $mBody = $this->t->escapeString($body);
        $now = gmdate('Y-m-d H:i:s');
        $created = $now;
        if ($createdDate !== false)
            $created = date('Y-m-d', strtotime($createdDate)) . substr($now, 10);
        if (!$this->isTempPage($pagePath)
            && ($revisable === true)
            && $thisonstitutesRevision($pagePath, $revision, $body, $now))
        {
            $q = "LOCK TABLES pages WRITE, `files` WRITE";
            $this->t->query($q);
            // Copy some of the old info to the new revision.
            $q = "
                SELECT notify, settings FROM pages
                WHERE id = $pageId AND revision = $revision
            ";
            $this->t->query($q);
            $r = $this->t->getNextRecord();
            $notify = $r['notify'];
            $settings = $r['settings'];
            $mSettings = $this->t->escapeString($settings);
            $newRevision = $this->getMaxRevision() + 1;
            $mKind = $this->t->escapeString($kind);
            $mPageName = $this->t->escapeString($pageName);
            $mPageUri = $this->t->escapeString($pageUri);
            $mMode = $this->t->escapeString($mode);
            $userId = $GLOBALS['pizza']['user']['id'];
            $name = $GLOBALS['pizza']['user']['firstName'] . ' ' . $GLOBALS['pizza']['user']['lastName'];
            $mName = $this->t->escapeString($name);
            $q = "
                INSERT INTO pages (
                    id, parentId, revision, kind, pageName, pageUri, body
                    , mode, userId, groupId, `name`, created, modified
                    , views, notify, settings, lockedBy, lockedOn
                )
                VALUES (
                    $pageId, $parentId, $newRevision, '$mKind', '$mPageName', '$mPageUri', '$mBody'
                    , '$mMode', $userId, $groupId, '$mName', '$created', '$now'
                    , 0, '$notify', '$mSettings', 0, '1970-01-01 00:00:00'
                )
            ";
            $this->t->query($q);
            $q = "UNLOCK TABLES";
            $this->t->query($q);
            return true;
        }
        // This does not constitute a new revision, so modify the current
        // revision in-place.
        if ($createdDate !== false)
        {
            $q = "
                UPDATE pages SET body = '$mBody', created = '$created', modified = '$now'
                WHERE id = $pageId AND revision = $revision
            ";
        }
        else
        {
            $q = "
                UPDATE pages SET body = '$mBody', modified = '$now'
                WHERE id = $pageId AND revision = $revision
            ";
        }
        return $this->t->query($q);
    }

    function getChildren($pagePath, $kind)
    {
        $pageId = $this->pagePathToPageId($pagePath);
        // Any number of "kind" parameters can be passed.
        $kinds = array();
        if (func_num_args() > 2)
            $kinds = array_slice(func_get_args(), 2);
        $kindsQ = "pages1.kind = '$kind'";
        foreach ($kinds as $k) $kindsQ .= " OR pages1.kind = '$k'";
        // Get the highest revision as the baseline.
        $revision = $this->getMaxRevision();
        if (hg('r') && is_numeric(gg('r')) && (intval(gg('r')) == gg('r')))
            $revision = gg('r');
        // Get highest revision <= maxRevision for each page.
        $q = "
            SELECT * FROM pages AS pages1
            INNER JOIN (
                SELECT id, MAX(revision) AS maxRevision FROM pages
                WHERE parentId = $pageId AND revision <= $revision
                GROUP BY id
            ) AS pages2
            ON pages1.id = pages2.id
            AND pages1.revision = pages2.maxRevision
            AND $kindsQ
        ";
        $this->t->query($q);
        $n = $this->t->num_rows;
        $childPages = array();
        for ($i = 0; $i < $n; $i++)
            $childPages[] = $this->t->getNextRecord();
        $userId = $GLOBALS['pizza']['user']['id'];
        $prunedPages = array();
        foreach ($childPages as $p)
        {
            // Skip temp pages.
            if ($this->isTempPage('/' . $p['pageUri'])) continue;
            if (substr($p['mode'], 0, 1) == 'n')
            {
                // Page is turned off.  Only an administrator or the page
                // owner can view it.  Note that a userId of 0 means the page
                // was created by a non-user; i.e. anonymous.
                if (!$this->isAdministrator()
                    && (($userId == 0) || ($userId != $p['userId']))) continue;
            }
            $prunedPages[$p['id']] = $p;
        }
        return $prunedPages;
    }

    function getFileContents($pagePath, $fileName, $revision = false)
    {
        // Returns full contents in-memory as a string.
        $mFileName = $this->t->escapeString($fileName);
        if ($revision === false)
        {
            $revision = $this->getCurrentRevision($pagePath, $fileName);
            if ($revision === false) return false;
        }
        $pageId = $this->pagePathToPageId($pagePath);
        if ($pageId === false) return false;
        $q = "
            SELECT id, contents AS c, mimeType FROM `files`
            WHERE pageId = $pageId AND fileName = '$mFileName' AND revision = $revision
        ";
        $this->t->query($q);
        if ($this->t->num_rows == 0) return false;
        $r = $this->t->getNextRecord();
        // Increment view count for audio types.
        if (substr($r['mimeType'], 0, 6) == 'audio/') $this->incrementFileViews($r['id']);
        if (($r['c'] == '') && ($this->storageRoot !== false))
        {
            // Since contents is empty, try from storage.
            $contents = $this->storageGet($pagePath, $fileName);
            if ($contents !== false) return $contents;
        }
        return $r['c'];
    }

    function getFileInfo($pagePath, $fileName = '')
    {
        // If fileName is given, then this returns the file info for that
        // file.  If not given, this returns for all files for this page.
        $pageId = $this->pagePathToPageId($pagePath);
        if ($pageId === false) return false;
        $mFileName = $this->t->escapeString($fileName);
        $revision = $this->getMaxRevision();
        if (hg('r') && is_numeric(gg('r')) && (intval(gg('r')) == gg('r')))
            $revision = gg('r');
        $where = "WHERE pageId = $pageId";
        if ($fileName != '') $where .= " AND fileName = '$mFileName'";
        $where .= " AND revision <= $revision";
        $q = "
            SELECT files1.id, pageId, revision, fileName, fileSize, mimeType, views, `text`, dimensions
            FROM `files` AS files1
            INNER JOIN (
                SELECT id, MAX(revision) AS maxRevision
                FROM `files`
                $where
                GROUP BY id
            ) AS files2
            ON files1.id = files2.id
            AND files1.revision = files2.maxRevision
            AND files1.mimeType != 'deleted'
            ORDER BY fileName
        ";
        $this->t->query($q);
        $n = $this->t->num_rows;
        if ($n == 0) return false;
        $fileInfo = array();
        for ($i = 0; $i < $n; $i++)
            $fileInfo[] = $this->t->getNextRecord();
        if ($fileName != '') $fileInfo = $fileInfo[0];
        return $fileInfo;
    }

    function getMenu($name)
    {
        $mName = $this->t->escapeString($name);
        $q = "SELECT `data` FROM menus WHERE `name` = '$mName'";
        $this->t->query($q);
        if ($this->t->num_rows == 0) return array();
        $r = $this->t->getNextRecord();
        $data = unserialize(trim($r['data']));
        return $data;
    }

    function getPage($pagePath)
    {
        $breadcrumbs = $this->getBreadcrumbs($pagePath);
        if ($breadcrumbs === false) return false;
        if (isset($breadcrumbs['isAlias']))
        {
            $actualPagePath = $breadcrumbs['actualPagePath'];
            if (isset($GLOBALS['pizza']['queryString']))
                $actualPagePath .= '?' . $GLOBALS['pizza']['queryString'];
            $url = $GLOBALS['pizza']['urlRoot'] . $actualPagePath;
            header('HTTP/1.1 301 Moved Permanently');
            header("Location: $url");
            exit();
        }
        $breadcrumbs = $breadcrumbs['breadcrumbs'];
        $n = count($breadcrumbs);
        if (isset($breadcrumbs[$n - 1]['fileName']))
        {
            // This page request is actually a file request.
            // Check for read permission of parent.
            beginSession('read-only');
            if (!$this->isReadable($breadcrumbs[$n - 2])) return false;
            // If accessed with a trailing slash, do a 404 (by way of
            // returning false to trigger a 404, as we can't call
            // error404() this early in.
            if (substr($GLOBALS['pizza']['pagePath'], -1) == '/')
                return false;
            $parentPageId = $breadcrumbs[$n - 2]['id'];
            $fileName = $breadcrumbs[$n - 1]['fileName'];
            $parentPagePath = $this->pageIdToPagePath($parentPageId);
            $this->dumpFile($parentPagePath, $fileName);
            // Only here if dump goes wrong.
            return false;
        }
        // If pageUri looks like '-...-', silently ignore with false.
        $pageUri = $breadcrumbs[$n - 1]['pageUri'];
        if (preg_match('/^-.*-$/', $pageUri)) return false;
        $pageId = $breadcrumbs[$n - 1]['id'];
        $revision = $breadcrumbs[$n - 1]['revision'];
        $q = "SELECT * FROM pages WHERE id = $pageId AND revision = $revision";
        $this->t->query($q);
        if ($this->t->num_rows == 0) return false;
        $page = $this->t->getNextRecord();
        // Unserialize the page's settings.
        if ($page['settings'] != '') $page['settings'] = unserialize($page['settings']);
        // Add the breadcrumbs to the page array.
        $page['breadcrumbs'] = $breadcrumbs;
        // Add Open Graph information.
        $page['openGraph'] = array(
            'title' => ($page['settings']['og-title'] ?? (
                $page['pageName'] != '__domain__'
                ? $page['pageName']
                : $GLOBALS['pizza']['config']['siteName'])),
            'description' => ($page['settings']['og-description'] ?? ''),
            'image' => ($page['settings']['og-image'] ?? ''),
            'url' => $GLOBALS['pizza']['urlRoot'] . $pagePath,
            'type' => 'article'
        );
        // If no og image given, try to find one.
        if ($page['openGraph']['image'] == '')
        {
            $files = $this->getFileInfo($pagePath);
            if ($files === false) $files = array();
            foreach ($files as $f)
            {
                // Use first image found.
                if (($f['mimeType'] == 'image/jpeg')
                    || ($f['mimeType'] == 'image/pjpeg')
                    || ($f['mimeType'] == 'image/gif')
                    || ($f['mimeType'] == 'image/png')
                    || ($f['mimeType'] == 'image/svg+xml')
                    || ($f['mimeType'] == 'image/webp'))
                {
                    // Encode the filename to convert space to plus.
                    $page['openGraph']['image'] = $GLOBALS['pizza']['urlRoot'] . $pagePath . urlencode($f['fileName']);
                    break;
                }
            }
        }
        return $page;
    }

    function getSitemap($pagePath = false)
    {
        // Get the highest revision as the baseline.
        $maxRevision = $this->getMaxRevision();
        if (hg('r') && is_numeric(gg('r')) && (intval(gg('r')) == gg('r')))
            $maxRevision = gg('r');
        // Get highest revision <= maxRevision for each page.
        $q = "
            SELECT pages1.id, parentId, revision, kind, pageName, pageUri, mode, userId, groupId, modified, notify
            FROM pages AS pages1
            INNER JOIN (
                SELECT id, MAX(revision) AS maxRevision FROM pages
                WHERE revision <= $maxRevision
                GROUP BY id
            ) AS pages2
            ON pages1.id = pages2.id
            AND pages1.revision = pages2.maxRevision
            AND pages1.kind != 'deleted'
            AND pages1.kind != 'reserved'
            AND pages1.kind NOT LIKE '%-entry'
            AND pages1.pageUri NOT LIKE '-%-'
        ";
        $this->t->query($q);
        $n = $this->t->num_rows;
        $pages = array();
        $userId = $GLOBALS['pizza']['user']['id'];
        for ($i = 0; $i < $n; $i++)
        {
            $r = $this->t->getNextRecord();
            // Skip temp pages.
            if ($this->isTempPage($r['pageUri'])) continue;
            // Skip if sandbox and sandbox disabled.
            if (($r['pageUri'] == 'sandbox') && ($r['parentId'] == 1)
                && !($GLOBALS['pizza']['config']['sandbox'] ?? false))
                continue;
            // If admin, include this page.
            if ($this->isAdministrator())
            {
                $pages[$r['id']] = $r;
                continue;
            }
            // Not an admin, so skip or restrict certain pages.
            if ($userId != $r['userId'])
            {
                $turnedOn = (substr($r['mode'], 0, 1) == 'y');
                $inSitemap = (substr($r['mode'], 1, 1) == 'y');
                if (!$turnedOn || !$inSitemap) continue;
                if (($r['pageUri'] == 'sandbox') && ($r['parentId'] == 1)) continue;
                if (!$this->isReadable($r)) $r['restricted'] = true;
            }
            $pages[$r['id']] = $r;
        }
        // Construct a tree, noting that the keys of $pages must equal the
        // id of each page (as is queried/assembled above).
        $tree = array();
        $treeIndex = array();
        while (count($pages) > 0)
        {
            foreach ($pages as $id => $page)
            {
                if ($page['parentId'] > 0)
                {
                    if (
                        !array_key_exists($page['parentId'], $pages)
                        && !array_key_exists($page['parentId'], $treeIndex)
                    ) {
                        // This is an orphan; unset/skip and continue.
                        unset($pages[$id]);
                        continue;
                    }
                    // Not an orphan.
                    if (array_key_exists($page['parentId'], $treeIndex))
                    {
                        $parent = &$treeIndex[$page['parentId']];
                        if (!isset($parent['children']))
                            $parent['children'] = array();
                        $parent['children'][$id] = $page;
                        $treeIndex[$id] = &$parent['children'][$id];
                        unset($pages[$id]);
                    }
                    continue;
                }
                // This is the top-most node; parentId = 0.
                $tree[$id] = $page;
                $treeIndex[$id] = &$tree[$id];
                unset($pages[$id]);
            }
        }
        $this->sortPageTree($tree);
        $topId = $this->pagePathToPageId($pagePath);
        if (($topId !== false) && !isset($treeIndex[$topId])) return false;
        if ($topId !== false)
        {
            $subTree = array($topId => $treeIndex[$topId]);
            return $subTree;
        }
        return $tree;
    }

    function getTheme($pagePath)
    {
        $breadcrumbs = $this->getBreadcrumbs($pagePath);
        while ($breadcrumbs === false)
        {
            // Go up the pagePath until some portion of it is legit.
            $pagePath = dirname($pagePath);
            if ($pagePath != '/') $pagePath .= '/';
            $breadcrumbs = $this->getBreadcrumbs($pagePath);
        }
        $breadcrumbs = $breadcrumbs['breadcrumbs'];
        return $this->getEffectiveTheme($breadcrumbs);
    }

    function getThemes()
    {
        $themePages = $this->getChildren('/themes/', 'theme');
        $themes = array();
        foreach ($themePages as $t)
            $themes[] = $t['pageName'];
        $docRoot = $GLOBALS['pizza']['docRoot'];
        if (is_dir("$docRoot/themes"))
            foreach (glob("$docRoot/themes/*", GLOB_ONLYDIR) as $d)
                $themes[] = basename($d);
        sort($themes);
        return $themes;
    }

    function getThemesInUse()
    {
        $tree = $this->getThemeableTree($this->pagePathToPageId('/'));
        $pages = $this->pageTreeToPageList($tree);
        $themes = array();
        foreach ($pages as $k => $p)
        {
            // Get p's settings.
            $revision = $p['revision'];
            $q = "SELECT settings FROM pages WHERE id = $k AND revision = $revision";
            $this->t->query($q);
            if ($this->t->num_rows == 0) continue;
            $r = $this->t->getNextRecord();
            $settings = array();
            if ($r['settings'] != '') $settings = unserialize($r['settings']);
            if (isset($settings['theme']))
            {
                $pagePath = $this->pageIdToPagePath($k);
                $themes[$settings['theme']][] = $pagePath;
            }
        }
        return $themes;
    }

    function githubCheckSandbox()
    {
        $url = "https://api.github.com/repos/flemingcomputer/pizza/contents/pizza_sandbox.sql";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 5000);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: flemingcomputer/1.0',
            'Accept: application/vnd.github+json'
        ]);
        $response = curl_exec($ch);
        if (curl_errno($ch)) return false;
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode != 200) return false;
        $data = json_decode($response, true);
        $newSha = substr($data['sha'], 0, 7);
        $this->t->query("SELECT sandbox FROM versions");
        $r = $this->t->getNextRecord();
        $oldSha = $r['sandbox'];
        // If the master -sandbox- is missing or not yet installed, return SHA.
        if (!$this->hasPage('/-sandbox-/')) return $newSha;
        // Return SHA if changed, false otherwise.
        return $newSha != $oldSha ? $newSha : false;
    }

    function hasFile($pagePath, $fileName)
    {
        $revision = $this->getCurrentRevision($pagePath, $fileName);
        if ($revision === false) return false;
        return true;
    }

    function hasPage($pagePath)
    {
        // Is this a mod-rewrite literal reserved page?
        if (in_array($pagePath, $GLOBALS['pizza']['reservedPages'])) return true;
        if (trim($pagePath) == '') return false;
        if (is_file($GLOBALS['pizza']['docRoot'] . $pagePath)) return true;
        $breadcrumbs = $this->getBreadcrumbs($pagePath);
        if ($breadcrumbs === false) return false;
        if (isset($breadcrumbs['isAlias'])) return false;
        return true;
    }

    function imageGetDimensions($pagePath, $fileName, $maxX = -1, $maxY = -1)
    {
        // If no maxX and maxY given, returns as-is dimensions.
        // Otherwise, returns dimensions within specified bounding box
        // preserving aspect ratio.
        $fileInfo = $this->getFileInfo($pagePath, $fileName);
        if ($fileInfo === false) return false;
        $parts = explode('x', $fileInfo['dimensions']);
        if (count($parts) != 2) return false;
        list($x, $y) = $parts;
        if (($maxX < 1) || ($maxY < 1))
            return array($x, $y);
        $px = $x / $maxX;
        $py = $y / $maxY;
        $p = $px > $py ? $px : $py;
        if ($p < 1) return array($x, $y);
        $rx = round($x / $p);
        $ry = round($y / $p);
        if ($rx == 0) $rx = 1;
        if ($ry == 0) $ry = 1;
        return array($rx, $ry);
    }

    function incrementViews()
    {
        // Don't increment for administrators.
        if ($this->isAdministrator()) return false;
        if (!isset($GLOBALS['pizza']['page']['mode'])) return false;
        // Don't increment RSS feed views.
        if (hg('podcast')) return false;
        $viewed = sge('viewed', array());
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $now = time();
        // Only register a new view for this session once per hour.
        if (isset($viewed[$pagePath])
            && ($now - $viewed[$pagePath] < 3600))
            return false;
        $id = $GLOBALS['pizza']['page']['id'];
        $revision = $GLOBALS['pizza']['page']['revision'];
        $q = "UPDATE pages SET views = views + 1 WHERE id = $id AND revision = $revision";
        $this->t->query($q);
        if ($this->t->affected_rows == 1)
        {
            $GLOBALS['pizza']['page']['views']++;
            $viewed[$pagePath] = $now;
            ss('viewed', $viewed);
        }
    }

    function inSitemap($pagePath = false)
    {
        if ($pagePath === false)
        {
            if (!isset($GLOBALS['pizza']['page']['mode'])) return false;
            $pageMode = $GLOBALS['pizza']['page']['mode'];
        }
        else
        {
            $page = $this->getPage($pagePath);
            if ($page === false) return false;
            $pageMode = $page['mode'];
        }
        return substr($pageMode, 1, 1) == 'y';
    }

    function isAdministrator()
    {
        $userId = $GLOBALS['pizza']['user']['id'];
        if ($userId == 0) return false;
        $groups = isset($GLOBALS['pizza']['user']['groups'])
            ? $GLOBALS['pizza']['user']['groups']
            : array();
        return in_array(1, array_keys($groups));
    }

    function isOwner($pageOrPath = false)
    {
        $userId = $GLOBALS['pizza']['user']['id'];
        if ($userId == 0) return false;
        if (isset($pageOrPath['userId']))
        {
            // It's a page array.
            $pageUserId = $pageOrPath['userId'];
        }
        else if (is_string($pageOrPath))
        {
            // It's a pagePath.
            $page = $this->getPage($pageOrPath);
            if ($page === false) return false;
            $pageUserId = $page['userId'];
        }
        else // false
        {
            // For current page.
            $pageUserId = $GLOBALS['pizza']['page']['userId'] ?? false;
            if ($pageUserId === false) return false;
        }
        return $userId == $pageUserId;
    }

    function isReadable($pageOrPath = false)
    {
        if (isset($pageOrPath['mode']))
        {
            // It's a page array.
            $pageMode = $pageOrPath['mode'];
            $pageUserId = $pageOrPath['userId'];
            $pageGroupId = $pageOrPath['groupId'];
        }
        else if (is_string($pageOrPath))
        {
            // It's a pagePath.
            $page = $this->getPage($pageOrPath);
            if ($page === false) return false;
            $pageMode = $page['mode'];
            $pageUserId = $page['userId'];
            $pageGroupId = $page['groupId'];
        }
        else // false
        {
            // For current page.
            if (!isset($GLOBALS['pizza']['page']['mode'])) return false;
            $pageMode = $GLOBALS['pizza']['page']['mode'];
            $pageUserId = $GLOBALS['pizza']['page']['userId'];
            $pageGroupId = $GLOBALS['pizza']['page']['groupId'];
        }
        $groups = isset($GLOBALS['pizza']['user']['groups'])
            ? $GLOBALS['pizza']['user']['groups']
            : array();
        // Always readable for administrators.
        if (in_array(1, array_keys($groups))) return true;
        // If owned by this user...
        if ($GLOBALS['pizza']['user']['id'] == $pageUserId)
        {
            if (substr($pageMode, 2, 1) == 'r') return true;
            else return false;
        }
        // If owned by a group with this user...
        if (in_array($pageGroupId, array_keys($groups)))
        {
            if (substr($pageMode, 4, 1) == 'r') return true;
            else return false;
        }
        // See if other-readable.
        if (substr($pageMode, 6, 1) == 'r') return true;
        return false;
    }

    function isReservedPage($pagePath)
    {
        // Is this a mod-rewrite literal reserved page?
        if (in_array($pagePath, $GLOBALS['pizza']['reservedPages'])) return true;
        $breadcrumbs = $this->getBreadcrumbs($pagePath);
        if ($breadcrumbs === false) return false;
        $breadcrumbs = $breadcrumbs['breadcrumbs'];
        $pageId = $breadcrumbs[count($breadcrumbs) - 1]['id'];
        $q = "SELECT kind FROM pages WHERE id = $pageId";
        $this->t->query($q);
        if ($this->t->num_rows == 0) return false;
        $r = $this->t->getNextRecord();
        return $r['kind'] == 'reserved';
    }

    function isTempPage($pagePath)
    {
        return preg_match('/\/temp\-[\d]{8}/', $pagePath);
    }

    function isTurnedOn($pagePath = false)
    {
        if ($pagePath === false)
        {
            if (!isset($GLOBALS['pizza']['page']['mode'])) return false;
            $pageMode = $GLOBALS['pizza']['page']['mode'];
        }
        else
        {
            $page = $this->getPage($pagePath);
            if ($page === false) return false;
            $pageMode = $page['mode'];
        }
        return substr($pageMode, 0, 1) == 'y';
    }

    function isWritable($pagePath = false)
    {
        if ($pagePath === false)
        {
            if (!isset($GLOBALS['pizza']['page']['mode'])) return false;
            $pageKind = $GLOBALS['pizza']['page']['kind'];
            $pageMode = $GLOBALS['pizza']['page']['mode'];
            $pageUserId = $GLOBALS['pizza']['page']['userId'];
            $pageGroupId = $GLOBALS['pizza']['page']['groupId'];
        }
        else
        {
            $page = $this->getPage($pagePath);
            if ($page === false) return false;
            $pageKind = $page['kind'];
            $pageMode = $page['mode'];
            $pageUserId = $page['userId'];
            $pageGroupId = $page['groupId'];
        }
        if ($pageKind == 'reserved') return false;
        $groups = isset($GLOBALS['pizza']['user']['groups'])
            ? $GLOBALS['pizza']['user']['groups']
            : array();
        // Always writable for administrators.
        if (in_array(1, array_keys($groups))) return true;
        // If owned by this user...
        if ($GLOBALS['pizza']['user']['id'] == $pageUserId)
        {
            if (substr($pageMode, 3, 1) == 'w') return true;
            else return false;
        }
        // If owned by a group with this user...
        if (in_array($pageGroupId, array_keys($groups)))
        {
            if (substr($pageMode, 5, 1) == 'w') return true;
            else return false;
        }
        // See if other-writable.
        if (substr($pageMode, 7, 1) == 'w') return true;
        return false;
    }

    function listPageNo($pagePath = false)
    {
        if (!$this->isAdministrator() && !$this->isOwner($pagePath)) return false;
        if ($pagePath === false)
            $pagePath = $GLOBALS['pizza']['pagePath'];
        else
            if (!$this->hasPage($pagePath)) return false;
        $sitemap = $this->getSitemap($pagePath);
        if ($sitemap === false) return false;
        $pages = $this->pageTreeToPageList($sitemap);
        // Construct comma-separated list of page IDs.
        $pageIds = '';
        foreach ($pages as $k => $p)
        {
            $pageIds .= $k . ',';
            if ($pagePath == '/') break; // If top page, don't recurse.
        }
        $pageIds = substr($pageIds, 0, -1);
        $q = "UPDATE pages SET mode = CONCAT(SUBSTRING(mode, 1, 1), 'n', SUBSTRING(mode, 3)) WHERE id IN ($pageIds)";
        $this->t->query($q);
    }

    function listPageYes($pagePath = false)
    {
        if (!$this->isAdministrator() && !$this->isOwner($pagePath)) return false;
        if ($pagePath === false)
            $pagePath = $GLOBALS['pizza']['pagePath'];
        else
            if (!$this->hasPage($pagePath)) return false;
        $sitemap = $this->getSitemap($pagePath);
        if ($sitemap === false) return false;
        $pages = $this->pageTreeToPageList($sitemap);
        // Construct comma-separated list of page IDs.
        $pageIds = '';
        foreach ($pages as $k => $p)
        {
            $pageIds .= $k . ',';
            if ($pagePath == '/') break; // If top page, don't recurse.
        }
        $pageIds = substr($pageIds, 0, -1);
        $q = "UPDATE pages SET mode = CONCAT(SUBSTRING(mode, 1, 1), 'y', SUBSTRING(mode, 3)) WHERE id IN ($pageIds)";
        $this->t->query($q);
    }

    function lockedBy()
    {
        $userId = $GLOBALS['pizza']['user']['id'];
        if ($userId == 0) return false;
        $lockedBy = $GLOBALS['pizza']['page']['lockedBy'] ?? false;
        if ($lockedBy === false) return false;
        if ($lockedBy == 0) return 0;
        $lockedOn = $GLOBALS['pizza']['page']['lockedOn'];
        // If the page is locked by the current user, then regard it as locked
        // regardless of time since nobody else is asking.
        if ($lockedBy == $userId) return $lockedBy;
        // Since somebody else is asking, see if the lock has expired for the
        // current user.
        $then = strtotime($lockedOn . ' UTC');
        if (time() - $then < 1 * 3600) return $lockedBy;
        return 0;
    }

    function lockPage()
    {
        $userId = $GLOBALS['pizza']['user']['id'];
        if ($userId == 0) return false;
        $id = $GLOBALS['pizza']['page']['id'];
        $revision = $GLOBALS['pizza']['page']['revision'];
        $lockedBy = $GLOBALS['pizza']['page']['lockedBy'];
        $lockedOn = $GLOBALS['pizza']['page']['lockedOn'];
        // An existing lock is good for 1 hour.
        $then = strtotime($lockedOn . ' UTC');
        // Refuse the lock to a new user if it's been less than 1 hour.
        if (($lockedBy != $userId) && (time() - $then < 1 * 3600)) return false;
        // Either a new user is taking over the lock, or the same user is
        // renewing the lock.
        $now = gmdate('Y-m-d H:i:s');
        $q = "
            UPDATE pages
            SET lockedBy = $userId, lockedOn = '$now'
            WHERE id = $id AND revision = $revision
        ";
        $this->t->query($q);
        // Update the locked state of page for downstream lockedBy() calls.
        $GLOBALS['pizza']['page']['lockedBy'] = $userId;
        $GLOBALS['pizza']['page']['lockedOn'] = $now;
        return true;
    }

    function pageHasChildren($pagePath)
    {
        $pageId = $this->pagePathToPageId($pagePath);
        if ($pageId === false) return false;
        $q = "
            SELECT pages1.id FROM pages AS pages1
            INNER JOIN (
                SELECT id, MAX(revision) AS maxRevision FROM pages
                WHERE parentId = $pageId
                GROUP BY id
            ) AS pages2
            ON pages1.id = pages2.id
            AND pages1.revision = pages2.maxRevision
            AND pages1.kind != 'deleted'
        ";
        $this->t->query($q);
        return $this->t->num_rows > 0;
    }

    function pageIdToPagePath($pageId)
    {
        // pagePath computations are cached.
        if (isset($this->pagePaths[$pageId]))
            return $this->pagePaths[$pageId];
        $pagePath = '/';
        while ($pageId != 0)
        {
            $q = "SELECT parentId, pageUri FROM pages WHERE id = $pageId";
            $this->t->query($q);
            if ($this->t->num_rows == 0) return false;
            $r = $this->t->getNextRecord();
            $pageId = $r['parentId'];
            $pageUri = $r['pageUri'];
            if ($pageUri != '__domain__') $pagePath = '/' . $pageUri . $pagePath;
        }
        // // Treat pagePath with 1-4 character file extension (e.g., ab.jpeg)
        // // as "file", and remove trailing slash.
        // if (preg_match('/\.\S{1,4}$/', $pagePath))
        //     $pagePath = substr($pagePath, 0, -1);
        $this->pagePaths[$pageId] = $pagePath;
        return $pagePath;
    }

    function pageNameToPageUri($name)
    {
        $uri = preg_replace(
            array('/[\/,_-]/', '/[^.a-z0-9\s]/i', '/\s+/'),
            array(' ', '', '-'),
            $name
        );
        return strtolower($uri);
    }

    function pagePathToPageId($pagePath)
    {
        $breadcrumbs = $this->getBreadcrumbs($pagePath);
        if ($breadcrumbs === false) return false;
        // The page of interest is the last link in the chain.
        $breadcrumbs = $breadcrumbs['breadcrumbs'];
        return $breadcrumbs[count($breadcrumbs) - 1]['id'];
    }

    function parentPath($pagePath)
    {
        return preg_replace('|/[^/]*/?$|', '/', $pagePath);
    }

    function renamePage($pagePath, $pageName)
    {
        // Do not rename the top page.
        if ($pagePath == '/') return false;
        // Do not rename a reserved page.
        if ($this->isReservedPage($pagePath)) return false;
        // Do not rename if pageName looks like a temp page name.
        if ($this->isTempPage($pageName)) return false;
        $breadcrumbs = $this->getBreadcrumbs($pagePath);
        if ($breadcrumbs === false) return false;
        $breadcrumbs = $breadcrumbs['breadcrumbs'];
        // Create new page info.
        $pageUri = $this->pageNameToPageUri($pageName);
        // If pageUri looks like '-...-', reserved.
        if (preg_match('/^-.*-$/', $pageUri)) return false;
        $mPageName = $this->t->escapeString($pageName);
        $mPageUri = $this->t->escapeString($pageUri);
        // Get current page info.
        $n = count($breadcrumbs);
        $parentId = $breadcrumbs[$n - 2]['id'];
        $pageId = $breadcrumbs[$n - 1]['id'];
        $currentPageName = $breadcrumbs[$n - 1]['pageName'];
        // If the pageName is the same, nothing to do.
        if ($currentPageName == $pageName) return true;
        $currentPageUri = $breadcrumbs[$n - 1]['pageUri'];
        $mCurrentPageName = $this->t->escapeString($currentPageName);
        $mCurrentPageUri = $this->t->escapeString($currentPageUri);
        // If renaming is not based on case alone, consider page alias issues.
        if (!$this->isTempPage($pagePath)
            && (strtolower($currentPageName) != strtolower($pageName)))
        {
            // Delete a same-parented page alias matching the proposed pageUri.
            $q = "
                DELETE FROM pageAliases
                WHERE (parentId = $parentId AND pageUri = '$mPageUri')
            ";
            $this->t->query($q);
            // Also delete all aliases for all pages that are older than 90 days.
            $q = "
                DELETE FROM pageAliases
                WHERE (UTC_TIMESTAMP() - INTERVAL 90 DAY > created)
            ";
            $this->t->query($q);
            // Next, keep up to five aliases for this page.
            $q = "SELECT COUNT(*) AS numAliases FROM pageAliases WHERE pageId = $pageId";
            $this->t->query($q);
            $r = $this->t->getNextRecord();
            $numAliases = $r['numAliases'];
            $deleteLimit = $numAliases - 5 + 1;  // An alias will be added.
            if ($deleteLimit > 0)
            {
                $q = "
                    DELETE FROM pageAliases
                    WHERE pageId = $pageId
                    ORDER BY created
                    LIMIT $deleteLimit
                ";
                $this->t->query($q);
            }
            // Save the current pageName/pageUri in the page aliases.
            $now = gmdate('Y-m-d H:i:s');
            $q = "
                INSERT INTO pageAliases (parentId, pageId, pageName, pageUri, created)
                VALUES ($parentId, $pageId, '$mCurrentPageName', '$mCurrentPageUri', '$now')
            ";
            $this->t->query($q);
        }
        // Reset the breadcrumbs cache.
        $this->breadcrumbs = array();
        // Rename the page in storage if any.
        $storageRoot = storageRoot();
        if ($storageRoot !== false)
        {
            $dirName = dirname($pagePath);
            if ($dirName != '/') $dirName .= '/';
            $oldDir = $storageRoot . $dirName . $currentPageUri;
            $newDir = $storageRoot . $dirName . $pageUri;
            if (is_dir($oldDir))
                rename($oldDir, $newDir);
        }
        // Update the page with the new name and URI.  Also update the <h1> in
        // the body if it matches the old name.
        $oldH1 = $this->t->escapeString("<h1>$currentPageName</h1>");
        $newH1 = $this->t->escapeString("<h1>$pageName</h1>");
        $q = "
            UPDATE pages
            SET pageName = '$mPageName', pageUri = '$mPageUri',
                body = REPLACE(body, '$oldH1', '$newH1')
            WHERE id = $pageId
        ";
        return $this->t->query($q);
    }

    function resetCache()
    {
        // From db.
        $q = "
            UPDATE `files` SET
            cache1 = '', cache1Dimensions = '', cache1Read = '1970-01-01 00:00:00',
            cache2 = '', cache2Dimensions = '', cache2Read = '1970-01-01 00:00:00',
            cache3 = '', cache3Dimensions = '', cache3Read = '1970-01-01 00:00:00'
        ";
        $this->t->query($q);
        // From storage.
        $targetDir = $this->storageRoot;
        if (!is_dir($targetDir)) return;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($targetDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $f)
        {
            if ($f->isDir())
                @rmdir($f->getRealPath());
            else if (preg_match('/.+\.cache\d+\.*/', $f->getRealPath(), $matches))
                unlink($f->getRealPath());
        }
    }

    function sandboxExport()
    {
        // This duplicates the master sandbox into separate tables for export.
        $breadcrumbs = $this->getBreadcrumbs('/-sandbox-/');
        if ($breadcrumbs === false) return false;
        $breadcrumbs = $breadcrumbs['breadcrumbs'];
        $srcPageId = $breadcrumbs[count($breadcrumbs) - 1]['id'];
        $srcPageUri = $breadcrumbs[count($breadcrumbs) - 1]['pageUri'];
        $breadcrumbs = $this->getBreadcrumbs('/');
        $breadcrumbs = $breadcrumbs['breadcrumbs'];
        $dstParentId = $breadcrumbs[count($breadcrumbs) - 1]['id'];
        // Fetch the entire branch sorted by depth (parents always come before children)
        $q = "
            WITH RECURSIVE treeBranch AS (
                SELECT *, 0 AS depth
                FROM pages
                WHERE id = $srcPageId
                UNION ALL
                SELECT c.*, tb.depth + 1
                FROM pages AS c
                JOIN treeBranch tb ON c.parentId = tb.id
            )
            SELECT * FROM treeBranch ORDER BY depth ASC;
        ";
        $this->t->query($q);
        $rows = $this->t->getAllRecords();
        if (empty($rows)) return false;
        // Drop existing sandbox export tables.
        $q = "DROP TABLE IF EXISTS sandboxPages";
        $this->t->query($q);
        $q = "DROP TABLE IF EXISTS sandboxFiles";
        $this->t->query($q);
        $q = "CREATE TABLE sandboxPages LIKE pages";
        $this->t->query($q);
        $q = "CREATE TABLE sandboxFiles LIKE files";
        $this->t->query($q);
        // Prepare the reusable insert statement.
        $insertStmt = $this->t->prepare("
            INSERT INTO sandboxPages (id, parentId, revision, kind, pageName, pageUri, body,
                mode, userId, groupId, name, created, modified, views, notify, settings,
                lockedBy, lockedOn)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        // Bind references to variables that we'll change inside the loop.
        $id = null;
        $parentId = null;
        $revision = null;
        $kind = null;
        $pageName = null;
        $pageUri = null;
        $body = null;
        $mode = null;
        $userId = null;
        $groupId = null;
        $name = null;
        $created = null;
        $modified = null;
        $views = null;
        $notify = null;
        $settings = null;
        $lockedBy = null;
        $lockedOn = null;
        $insertStmt->bind_param('iiisssssiisssissis', $id, $parentId, $revision, $kind,
            $pageName, $pageUri, $body, $mode, $userId, $groupId, $name, $created,
            $modified, $views, $notify, $settings, $lockedBy, $lockedOn);
        // Loop through and duplicate nodes top-down.
        foreach ($rows as $row)
        {
            $id = $row['id'];
            $parentId = $row['parentId'];
            $revision = $row['revision'];
            $kind = $row['kind'];
            $pageName = $row['pageName'];
            $pageUri = $row['pageUri'];
            $body = $row['body'];
            $mode = $row['mode'];
            $userId = $row['userId'];
            $groupId = $row['groupId'];
            $name = $row['name'];
            $created = $row['created'];
            $modified = $row['modified'];
            $views = $row['views'];
            $notify = $row['notify'];
            $settings = $row['settings'];
            $lockedBy = $row['lockedBy'];
            $lockedOn = $row['lockedOn'];
            // Execute the insert
            $insertStmt->execute();
        }
        // Finish up.
        $insertStmt->close();
        // Copy associated files into sandboxFiles.
        // Determine number of cache columns to fill with initial values.
        $q = "SHOW COLUMNS FROM `files` LIKE 'cache%Dimensions'";
        $this->t->query($q);
        $numCaches = $this->t->num_rows;
        // Skipping the first entry parent-of-tree, copy each old page's
        // files to the corresponding new page.
        $rows = array_slice($rows, 1, null, true);
        if ($this->storageRoot !== false)
        {
            // We need to augment the rows with pagePath for possibly getting
            // contents from storage. (Query overlap occurs if not done here.)
            foreach ($rows as $i => $row)
                $rows[$i]['pagePath'] = $this->pageIdToPagePath($row['id']);
        }
        foreach ($rows as $row)
        {
            $pageId = $row['id'];
            $pagePath = $row['pagePath'] ?? false;
            $q = "SELECT * FROM files WHERE pageId = $pageId";
            $this->t->query($q);
            $n = $this->t->num_rows;
            for ($i = 0; $i < $n; $i++)
            {
                $r = $this->t->getNextRecord();
                $id = $r['id'];
                $pageId = $r['pageId'];
                $revision = $r['revision'];
                $fileName = $r['fileName'];
                $fileSize = $r['fileSize'];
                $mimeType = $this->t->escapeString($r['mimeType']);
                $views = $r['views'];
                $text = $this->t->escapeString($r['text']);
                $contents = $r['contents'];
                // We must put contents into sandboxFiles as database, but it
                // might be coming from storage.
                if (($contents == '') && ($this->storageRoot !== false))
                {
                    // Since contents is empty, try from storage.
                    $contents = $this->storageGet($pagePath, $fileName);
                    if ($contents === false) $contents = '';
                }
                $fileName = $this->t->escapeString($fileName);
                $contents = $this->t->escapeString($contents);
                $dimensions = $this->t->escapeString($r['dimensions']);
                $cacheFields = '';
                $cacheValues = '';
                for ($j = 1; $j <= $numCaches; $j++)
                {
                    $cacheFields .= ", cache{$j}, cache{$j}Dimensions, cache{$j}Read";
                    $cacheValues .= ", '', '', '1970-01-01 00:00:00'";
                }
                $q = "
                    INSERT INTO sandboxFiles (id, pageId, revision, fileName, fileSize, mimeType,
                        views, text, contents, dimensions$cacheFields)
                    VALUES ($id, $pageId, $revision, '$fileName', $fileSize, '$mimeType',
                        $views, '$text', '$contents', '$dimensions'$cacheValues)
                ";
                $this->t->query($q);
            }
        }
        // Export to pizza_sandbox.sql.
        $sqlFile = sys_get_temp_dir() . 'pizza_sandbox.sql';
        $host = $GLOBALS['pizza']['config']['dbHost'];
        $user = $GLOBALS['pizza']['config']['dbUser'];
        $password = $GLOBALS['pizza']['config']['dbPassword'];
        $database = $GLOBALS['pizza']['config']['dbDatabase'];
        $command = "/usr/local/mysql/bin/mysqldump --host=$host --user=$user --password=$password --add-drop-table \"$database\" sandboxPages sandboxFiles > $sqlFile";
        system($command, $output);
        // Drop the temp tables.
        $q = "DROP TABLE IF EXISTS sandboxPages";
        $this->t->query($q);
        $q = "DROP TABLE IF EXISTS sandboxFiles";
        $this->t->query($q);
        if ($output !== 0)
        {
            unlink($sqlFile);
            return false;
        }
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="pizza_sandbox.sql"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile($sqlFile);
        unlink($sqlFile);
        exit();
    }

    function sandboxUpdate()
    {
        // Get the sandbox contents from GitHub.
        $url = 'https://raw.githubusercontent.com/flemingcomputer/pizza/main/pizza_sandbox.sql';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: flemingcomputer/1.0',
            'Accept: application/vnd.github.v3.raw'
        ]);
        $sql = curl_exec($ch);
        if (curl_errno($ch)) return false;
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode != 200) return false;
        // Import the SQL.
        $result = $this->t->multi_query($sql);
        if ($result === false) return false;
        // Flush multi-query results.
        while ($this->t->next_result())
        {
            if ($result = $this->t->store_result())
                $result->free();
        }
        // Delete the master and user sandboxes.
        $this->deleteTree('/-sandbox-/');
        $this->deleteTree('/sandbox/');
        // Calculate offsets for the ID fields and copy sandbox tables to live tables.
        $q = "LOCK TABLES versions WRITE, pages WRITE, files WRITE, sandboxPages WRITE, sandboxFiles WRITE";
        $this->t->query($q);
        $q = "SELECT MAX(id) AS lastId FROM pages";
        $this->t->query($q);
        $r = $this->t->getNextRecord();
        $lastId = $r['lastId'];
        $q = "SELECT MIN(id) AS firstId FROM sandboxPages";
        $this->t->query($q);
        $r = $this->t->getNextRecord();
        $firstId = $r['firstId'];
        $pageOffset = $lastId - $firstId + 1;
        $q = "SELECT MAX(id) AS lastId FROM files";
        $this->t->query($q);
        $r = $this->t->getNextRecord();
        $lastId = $r['lastId'];
        $q = "SELECT MIN(id) AS firstId FROM sandboxFiles";
        $this->t->query($q);
        $r = $this->t->getNextRecord();
        $firstId = $r['firstId'];
        $fileOffset = $lastId - $firstId + 1;
        // Adjust every id and parentId (except topmost) in sandboxPages by pageOffset.
        $q = "UPDATE sandboxPages SET id = id + $pageOffset";
        $this->t->query($q);
        $q = "
            UPDATE sandboxPages SET parentId = parentId + $pageOffset
            WHERE pageUri != '-sandbox-'
        ";
        $this->t->query($q);
        // Adjust every id in sandboxFiles by fileOffset.
        $q = "UPDATE sandboxFiles SET id = id + $fileOffset";
        $this->t->query($q);
        // Adjust every pageId in sandboxFiles by pageOffset.
        $q = "UPDATE sandboxFiles SET pageId = pageId + $pageOffset";
        $this->t->query($q);
        // Copy all rows from temps to live tables.
        $q = "INSERT INTO pages SELECT * FROM sandboxPages";
        $this->t->query($q);
        $q = "INSERT INTO files SELECT * FROM sandboxFiles";
        $this->t->query($q);
        $sha = sge('sandboxSha', 'nothing');
        $q = "UPDATE versions SET sandbox = '$sha'";
        $this->t->query($q);
        $q = "UNLOCK TABLES";
        $this->t->query($q);
        // Drop the temp tables.
        $q = "DROP TABLE IF EXISTS sandboxPages";
        $this->t->query($q);
        $q = "DROP TABLE IF EXISTS sandboxFiles";
        $this->t->query($q);
        // Reset the breadcrumbs cache before attempt copyTree.
        $this->breadcrumbs = array();
        // Copy master to user sandbox.
        $this->copyTree('/-sandbox-/', '/', 'Sandbox');
        return true;
    }

    function saveMenu($name, $menu)
    {
        $mName = $this->t->escapeString($name);
        $q = "DELETE FROM menus WHERE `name` = '$mName'";
        $this->t->query($q);
        if (count($menu) == 0) return;
        $data = $this->t->escapeString(serialize($menu));
        $q = "INSERT INTO menus (`name`, `data`) VALUES ('$mName', '$data')";
        $this->t->query($q);
    }

    // // unused - we now do 'notified' in Users table.
    // function setNotify($pagePath)
    // {
    //     $id = $this->pagePathToPageId($pagePath);
    //     if ($id === false) return false;
    //     // Get highest revision for this page.
    //     $q = "SELECT MAX(revision) AS maxRevision FROM pages WHERE id = $id";
    //     $this->t->query($q);
    //     if ($this->t->num_rows == 0) return false;
    //     $r = $this->t->getNextRecord();
    //     $maxRevision = $r['maxRevision'];
    //     $now = gmdate('Y-m-d H:i:s');
    //     $q = "
    //         UPDATE pages SET notify = '$now'
    //         WHERE id = $id AND revision = $maxRevision
    //         AND kind != 'deleted'
    //     ";
    //     $this->t->query($q);
    // }

    function setTheme($pagePath, $newTheme, $setSubpages = false)
    {
        $breadcrumbs = $this->getBreadcrumbs($pagePath);
        if ($breadcrumbs === false) return false;
        $breadcrumbs = $breadcrumbs['breadcrumbs'];
        $n = count($breadcrumbs);
        $id = $breadcrumbs[$n - 1]['id'];
        // Setting this page along with subpages is easy; just clear the theme
        // setting of all subpages.
        // However, setting just this page's theme is harder.
        // If children are inheriting theme from parent, and parent is changing
        // its theme, then children will need their theme set to parent's old
        // (effective) theme.
        // But if children have a theme set, and parent changes to that theme,
        // then children should clear their theme and inherit.
        // Get pagePath's theme before changing.
        $oldTheme = $this->getEffectiveTheme($breadcrumbs);
        $tree = $this->getThemeableTree($id);
        $pages = $this->pageTreeToPageList($tree);
        foreach ($pages as $k => $p)
        {
            // If not setting subpages, only consider top page and children.
            if (!$setSubpages && ($k != $id) && ($p['parentId'] != $id)) continue;
            // Get p's settings.
            $revision = $p['revision'];
            $q = "SELECT settings FROM pages WHERE id = $k AND revision = $revision";
            $this->t->query($q);
            if ($this->t->num_rows == 0) continue;
            $r = $this->t->getNextRecord();
            $settings = array();
            if ($r['settings'] != '') $settings = unserialize($r['settings']);
            // Now set p's theme accordingly.
            if ($k == $id)
                $settings['theme'] = $newTheme; // top page
            else if ($setSubpages)
                unset($settings['theme']);
            else if (!isset($settings['theme']) && ($oldTheme != $newTheme))
                $settings['theme'] = $oldTheme;
            else if (isset($settings['theme']) && ($settings['theme'] == $newTheme))
                unset($settings['theme']);
            $mSettings = $this->t->escapeString(serialize($settings));
            if ($mSettings == 'a:0:{}') $mSettings = '';
            $q = "
                UPDATE pages SET settings = '$mSettings'
                WHERE id = $k AND revision = $revision
            ";
            $this->t->query($q);
        }
    }

    function turnOff($pagePath = false)
    {
        // Turning on or off is not the same as writable.  An administrator
        // or the page owner can turn on or off, but nobody else can.
        if (!$this->isAdministrator() && !$this->isOwner($pagePath)) return false;
        if ($pagePath === false)
            $pagePath = $GLOBALS['pizza']['pagePath'];
        else
            if (!$this->hasPage($pagePath)) return false;
        $sitemap = $this->getSitemap($pagePath);
        if ($sitemap === false) return false;
        $pages = $this->pageTreeToPageList($sitemap);
        // Construct comma-separated list of page IDs.
        $pageIds = '';
        foreach ($pages as $k => $p)
        {
            $pageIds .= $k . ',';
            if ($pagePath == '/') break; // If top page, don't recurse.
        }
        $pageIds = substr($pageIds, 0, -1);
        $q = "UPDATE pages SET mode = CONCAT('n', SUBSTRING(mode, 2)) WHERE id IN ($pageIds)";
        $this->t->query($q);
    }

    function turnOn($pagePath = false)
    {
        // Turning on or off is not the same as writable.  An administrator
        // or the page owner can turn on or off, but nobody else can.
        if (!$this->isAdministrator() && !$this->isOwner($pagePath)) return false;
        if ($pagePath === false)
            $pagePath = $GLOBALS['pizza']['pagePath'];
        else
            if (!$this->hasPage($pagePath)) return false;
        $sitemap = $this->getSitemap($pagePath);
        if ($sitemap === false) return false;
        $pages = $this->pageTreeToPageList($sitemap);
        // Construct comma-separated list of page IDs.
        $pageIds = '';
        foreach ($pages as $k => $p)
        {
            $pageIds .= $k . ',';
            if ($pagePath == '/') break; // If top page, don't recurse.
        }
        $pageIds = substr($pageIds, 0, -1);
        $q = "UPDATE pages SET mode = CONCAT('y', SUBSTRING(mode, 2)) WHERE id IN ($pageIds)";
        $this->t->query($q);
    }

    function unlockPage()
    {
        $userId = $GLOBALS['pizza']['user']['id'];
        if ($userId == 0) return false;
        if (!isset($GLOBALS['pizza']['page']['mode'])) return false;
        $id = $GLOBALS['pizza']['page']['id'];
        $revision = $GLOBALS['pizza']['page']['revision'];
        // Only the owner of the lock can unlock the page.
        if ($GLOBALS['pizza']['page']['lockedBy'] != $userId) return false;
        $q = "
            UPDATE pages
            SET lockedBy = 0, lockedOn = '1970-01-01 00:00:00'
            WHERE id = $id AND revision = $revision
        ";
        $this->t->query($q);
        // Update the locked state of page for downstream lockedBy() calls.
        $GLOBALS['pizza']['page']['lockedBy'] = 0;
        $GLOBALS['pizza']['page']['lockedOn'] = '1970-01-01 00:00:00';
        return true;
    }

    function unlockPages($userId)
    {
        // Unlock all pages for the given user; e.g. logging out.
        if ($userId == 0) return false;
        $q = "
            UPDATE pages
            SET lockedBy = 0, lockedOn = '1970-01-01 00:00:00'
            WHERE lockedBy = $userId
        ";
        $this->t->query($q);
        return true;
    }

    function updateFileText($pagePath, $fileName, $text)
    {
        $mFileName = $this->t->escapeString($fileName);
        $mText = $this->t->escapeString($text);
        $revision = $this->getCurrentRevision($pagePath, $fileName);
        if ($revision === false) return false;
        $pageId = $this->pagePathToPageId($pagePath);
        $q = "
            UPDATE `files`
            SET `text` = '$mText'
            WHERE pageId = $pageId AND fileName = '$mFileName' AND revision = $revision
        ";
        $this->t->query($q);
    }

    private function constitutesRevision($pagePath, $revision, $body, $now)
    {
        return false;
    }

    private function deleteAllRevisions()
    {
        // Same procedure for pages and files.  Afterward, delete from
        // files where there are dangling page references.
        $table1 = array('pages', 'kind');
        $table2 = array('files', 'mimeType');
        $tables = array($table1, $table2);
        foreach ($tables as $table)
        {
            $tt = $table[0]; $ff = $table[1];
            $q = "SELECT id, revision, $ff FROM $tt ORDER BY id, revision";
            $this->t->query($q);
            $rows = array();
            $n = $this->t->num_rows;
            $oldId = 0;
            for ($i = 0; $i < $n; $i++)
            {
                $r = $this->t->getNextRecord();
                if (($r['id'] > $oldId) && ($oldId > 0))
                {
                    // Pop off the last entry unless it is 'deleted'.
                    $last = array_pop($rows);
                    if ($last[$ff] == 'deleted') array_push($rows, $last);
                }
                $rows[] = $r;
                $oldId = $r['id'];
            }
            if (!empty($rows))
            {
                $last = array_pop($rows);
                if ($last[$ff] == 'deleted') array_push($rows, $last);
            }
            foreach ($rows as $row)
            {
                $id = $row['id']; $revision = $row['revision'];
                $q = "DELETE FROM $tt WHERE id = $id AND revision = $revision";
                $this->t->query($q);
            }
            // Reset the revision number to 1.
            // $q = "UPDATE $tt SET revision = 1";
            // $this->t->query($q);
        }
        $q = "DELETE FROM `files` WHERE pageId NOT IN (SELECT id FROM pages)";
        $this->t->query($q);
    }

    private function deleteLowerRevisions($rn)
    {
        // Same procedure for pages and files.  Afterward, delete from
        // files where there are dangling page references.
        $table1 = array('pages', 'kind');
        $table2 = array('files', 'mimeType');
        $tables = array($table1, $table2);
        foreach ($tables as $table)
        {
            $tt = $table[0]; $ff = $table[1];
            $q = "SELECT id, revision, $ff FROM $tt ORDER BY id, revision";
            $this->t->query($q);
            $rows = array();
            $n = $this->t->num_rows;
            $oldId = 0;
            for ($i = 0; $i < $n; $i++)
            {
                $r = $this->t->getNextRecord();
                if ($r['id'] > $oldId)
                {
                    $skipAhead = false;
                    if ($oldId != 0)
                    {
                        $last = array_pop($rows);
                        if ($last[$ff] == 'deleted') array_push($rows, $last);
                    }
                }
                if ($skipAhead) continue;
                $rows[] = $r;
                if ($r['revision'] >= $rn) $skipAhead = true;
                $oldId = $r['id'];
            }
            if (!empty($rows))
            {
                $last = array_pop($rows);
                if ($last[$ff] == 'deleted') array_push($rows, $last);
            }
            foreach ($rows as $row)
            {
                $id = $row['id']; $revision = $row['revision'];
                $q = "DELETE FROM $tt WHERE id = $id AND revision = $revision";
                $this->t->query($q);
            }
        }
        $q = "DELETE FROM `files` WHERE pageId NOT IN (SELECT id FROM pages)";
        $this->t->query($q);
    }

    private function deleteOlderRevisions($dateTime)
    {
        // Files have no date fields.  So delete from pages, and then delete
        // from files where there are dangling page references.
        $q = "SELECT id, kind, modified FROM pages ORDER BY id, revision";
        $this->t->query($q);
        $rows = array();
        $n = $this->t->num_rows;
        $oldId = 0;
        for ($i = 0; $i < $n; $i++)
        {
            $r = $this->t->getNextRecord();
            if ($r['id'] > $oldId)
            {
                $skipAhead = false;
                if ($oldId != 0)
                {
                    $last = array_pop($rows);
                    if ($last['kind'] == 'deleted') array_push($rows, $last);
                }
            }
            if ($skipAhead) continue;
            $rows[] = $r;
            if ($r['modified'] >= $dateTime) $skipAhead = true;
            $oldId = $r['id'];
        }
        if (!empty($rows))
        {
            $last = array_pop($rows);
            if ($last['kind'] == 'deleted') array_push($rows, $last);
        }
        foreach ($rows as $row)
        {
            $id = $row['id']; $modified = $row['modified'];
            $q = "DELETE FROM pages WHERE id = $id AND modified = '$modified'";
            $this->t->query($q);
        }
        $q = "DELETE FROM `files` WHERE pageId NOT IN (SELECT id FROM pages)";
        $this->t->query($q);
    }

    private function dumpContents($pagePath, $fileName, $revision = false, $cacheLevel = 0)
    {
        // Determine if a byte range is requested.
        $start = null;
        $end = null;
        if (isset($_SERVER['HTTP_RANGE']))
        {
            $range = str_replace('bytes=', '', $_SERVER['HTTP_RANGE']);
            $parts = explode('-', $range);
            $start = intval($parts[0]);
            if (!empty($parts[1]))
                $end = intval($parts[1]);
            // error_log("Byte range requested from $start to $end");
        }
        // Determine if getting from contents or cache.
        $mFileName = $this->t->escapeString($fileName);
        if ($revision === false)
        {
            $revision = $this->getCurrentRevision($pagePath, $fileName);
            if ($revision === false) return false;
        }
        if ($cacheLevel > 0)
            $this->touchCache($pagePath, $fileName, $revision, $cacheLevel);
        // Try storage first.
        if ($this->storageRoot !== false)
        {
            $this->storageDump($pagePath, $fileName, $start, $end, $cacheLevel);
            // Only here if file isn't in storage or storage otherwise failed.
            if ($cacheLevel > 0)
            {
                // Requesting cached version that is in db but not on disk.
                // Returning false will allow dumpImage to proceed to regenerate the
                // cached version.
                return false;
            }
        }
        // Try from database.
        $contentsQ = 'contents';
        if ($cacheLevel > 0)
            $contentsQ = "cache$cacheLevel";
        // Construct and run query of either entire or partial contents.
        if ($start === null)
        {
            // Entire contents.
            $q = "$contentsQ AS c";
        }
        else if ($start < 0)
        {
            // From negative start onwward.
            $q = "SUBSTR($contentsQ, GREATEST($start, -LENGTH($contentsQ))) AS c";
        }
        else if (($start >= 0) && ($end === null))
        {
            // From positive start onward.
            $qStart = $start + 1;
            $q = "SUBSTR($contentsQ, $qStart) AS c";
        }
        else
        {
            // From positive start to given end.
            $qStart = $start + 1;
            $qEnd = $end - $start + 1;
            $q = "SUBSTR($contentsQ, $qStart, $qEnd) AS c";
        }
        // Do the query.
        $pageId = $this->pagePathToPageId($pagePath);
        if ($pageId === false) return false;
        $q = "
            SELECT $q, mimeType, LENGTH($contentsQ) AS totalSize
            FROM `files`
            WHERE pageId = $pageId AND fileName = '$mFileName' AND revision = $revision
        ";
        // error_log("q: " . trim(preg_replace('/\s\s+/', ' ', $q)));
        $this->t->query($q);
        if ($this->t->num_rows == 0) return false;
        $r = $this->t->getNextRecord();
        $mimeType = $r['mimeType'];
        // Increment view count for audio types.
        if (substr($mimeType, 0, 6) == 'audio/') $this->incrementFileViews($id);
        $contents = $r['c'];
        if (($cacheLevel > 0) && ($r['c'] == ''))
        {
            // Requesting cached version that is in db but its contents are empty.
            // Returning false will allow dumpImage to proceed to regenerate the
            // cached version.
            return false;
        }
        $totalSize = $r['totalSize'];
        $length = strlen($contents);
        if (($totalSize == 0) || ($length == 0))
            $start = null; // Forget range, do 200 OK for zero bytes
        else if ($start !== null)
        {
            // Convert HTTP_RANGE values to Content-Range equivalents.
            if ($start < 0)
            {
                // s,e,t = s-e/t
                // -3,n,10 = 7-9/10
                // -3,n,3 = 0-2/3
                // -3,n,2 = 0-1/2
                // -3,n,1 = 0-0/1
                $start = max(0, $totalSize + $start);
            }
            else if ($end === null)
                $end = $totalSize - 1;
        }
        $this->dumpFileHeaders($fileName, $mimeType, $length, $start, $end, $totalSize);
        set_time_limit(0);
        echo $contents;
        exit();
    }

    private function dumpFileHeaders($fileName, $mimeType, $length, $start, $end, $totalSize)
    {
        if (hg('nc'))
            header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
        else
            header('Cache-Control: max-age=1800');
        header('Accept-Ranges: bytes');
        header("Content-Type: $mimeType");
        header("Content-Disposition: inline; filename=\"$fileName\"");
        if ($start !== null)
        {
            header('HTTP/1.1 206 Partial Content');
            header('Content-Length: ' . $length);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $totalSize);
            // error_log("HTTP_RANGE: " . $_SERVER['HTTP_RANGE']);
            // error_log("start,end,total: " . $start . ',' . $end . ',' . $totalSize);
            // error_log("content 206 length: " . $length);
        }
        else
        {
            header('HTTP/1.1 200 OK');
            header('Content-Length: ' . $length);
            // error_log("content 200 length: " . $length);
        }
    }

    private function dumpImage($pagePath, $fileName, $newX, $newY)
    {
        // This performs arbitrary resizing to $newX x $newY.
        // If any of the stored image versions matches the requested
        // dimensions, return that image as-is.  Otherwise, resize and
        // cache-then-return the resized image.
        $fileInfo = $this->getFileInfo($pagePath, $fileName);
        if ($fileInfo === false) return false;
        // Try original.
        if ($fileInfo['dimensions'] != '')
        {
            $parts = explode('x', $fileInfo['dimensions']);
            if (count($parts) != 2) return false;
            list($oldX, $oldY) = $parts;
            if (($newX == $oldX) && ($newY == $oldY))
            {
                // error_log("dumpImage: using original");
                $this->dumpContents($pagePath, $fileName, $fileInfo['revision']);
            }
        }
        // Try the caches.
        $caches = $this->getCacheInfo($pagePath, $fileName, $fileInfo['revision']);
        foreach ($caches as $i => $dims)
        {
            if ($dims == '') continue;
            list($oldX, $oldY) = explode('x', $dims);
            if (($newX == $oldX) && ($newY == $oldY))
            {
                // error_log("dumpImage: using cache $i");
                $this->dumpContents($pagePath, $fileName, $fileInfo['revision'], $i);
            }
        }
        // No image versions match the request, so resize and cache.
        $contents = $this->getFileContents($pagePath, $fileName, $fileInfo['revision']);
        $im = new Imagick();
        $im->readImageBlob($contents);
        $contents = $this->imageResize($fileInfo['mimeType'], $im, $newX, $newY);
        $i = $this->imageToCache(count($caches), $pagePath, $fileName, $fileInfo['revision'], $contents, $newX, $newY);
        // error_log("dumpImage: new cache $i");
        $this->dumpContents($pagePath, $fileName, $fileInfo['revision'], $i);
    }

    private function generateInitialBody($pageName)
    {
        ob_start();
        ?>
        <h1><?=myHtmlEntities($pageName)?></h1>
        <?php
        $body = trim(ob_get_clean()) . "\n";
        return $body;
    }

    private function getBreadcrumbs($pagePath)
    {
        // Breadcrumb computations are cached.
        if (isset($this->breadcrumbs[$pagePath]))
            return $this->breadcrumbs[$pagePath];
        // Get the highest revision as the baseline.
        $revision = $this->getMaxRevision();
        if (hg('r') && is_numeric(gg('r')) && (intval(gg('r')) == gg('r')))
            $revision = gg('r');
        // Start at the top __domain__ and forward-construct from there.
        $parts = $this->parsePagePath($pagePath);
        if ($parts === false) return false;
        $n = count($parts);
        $breadcrumbs = array();
        $q = "
            SELECT id, revision, kind, pageName, pageUri, mode, userId, groupId, settings
            FROM pages
            WHERE pageUri = '__domain__' AND parentId = 0
            AND revision <= $revision
            ORDER BY revision DESC
            LIMIT 1
        ";
        $this->t->query($q);
        if ($this->t->num_rows == 0)
        {
            $this->breadcrumbs[$pagePath] = false;
            return false;
        }
        $r = $this->t->getNextRecord();
        if ($r['settings'] != '') $r['settings'] = unserialize($r['settings']);
        $breadcrumbs[] = $r;
        $parentId = $breadcrumbs[0]['id'];
        $actualPagePath = '';
        $isAlias = false;
        for ($i = 0; $i < $n; $i++)
        {
            $mPageUri = $this->t->escapeString($parts[$i]);
            $q = "
                SELECT * FROM (
                    SELECT id, revision, kind, pageName, pageUri, mode, userId, groupId, settings
                    FROM pages
                    WHERE pageUri = REPLACE('$mPageUri', '_', '-') AND parentId = $parentId
                    AND revision <= $revision
                    ORDER BY revision DESC
                    LIMIT 1
                ) AS pages2 WHERE kind != 'deleted'
            ";
            $this->t->query($q);
            if ($this->t->num_rows == 1)
            {
                $r = $this->t->getNextRecord();
                if ($r['settings'] != '') $r['settings'] = unserialize($r['settings']);
                $breadcrumbs[] = $r;
                $pageUri = $breadcrumbs[$i + 1]['pageUri'];
                $actualPagePath .= '/' . $pageUri;
                $parentId = $breadcrumbs[$i + 1]['id'];
                continue;
            }
            // This path part is either a page alias, a file request, or nonexistent.
            // If this is the last path part, see if it's a file request.
            if ($i == $n - 1)
            {
                $q = "
                    SELECT id, fileName FROM (
                        SELECT id, fileName, mimeType FROM `files`
                        WHERE pageId = $parentId AND fileName = '$mPageUri'
                        AND revision <= $revision
                        ORDER BY revision DESC
                        LIMIT 1
                    ) AS files2 WHERE mimeType != 'deleted'
                ";
                $this->t->query($q);
                if ($this->t->num_rows == 1)
                {
                    $breadcrumbs[] = $this->t->getNextRecord();
                    $fileName = $breadcrumbs[$i + 1]['fileName'];
                    $actualPagePath .= '/' . $fileName;
                    break;
                }
            }
            // Determine if this path part is an alias.
            $q = "
                SELECT pageId AS id, pageName, pageUri FROM pageAliases
                WHERE pageUri = REPLACE('$mPageUri', '_', '-') AND parentId = $parentId
            ";
            $this->t->query($q);
            if ($this->t->num_rows == 0)
            {
                $this->breadcrumbs[$pagePath] = false;
                return false;
            }
            $breadcrumbs[] = $this->t->getNextRecord();
            // This path part is an alias.  Find the actual path part to
            // be added to the actualPagePath.
            $isAlias = true;
            $pageId = $breadcrumbs[$i + 1]['id'];
            $q = "SELECT pageUri FROM pages WHERE id = $pageId";
            $this->t->query($q);
            // It shouldn't happen that an orphaned alias exists, but...
            if ($this->t->num_rows == 0)
            {
                $this->breadcrumbs[$pagePath] = false;
                return false;
            }
            // Okay, we're good to go with the actual path part.
            $r = $this->t->getNextRecord();
            $pageUri = $r['pageUri'];
            $actualPagePath .= '/' . $pageUri;
            $parentId = $breadcrumbs[$i + 1]['id'];
        }
        if ($isAlias === true) $this->breadcrumbs[$pagePath]['isAlias'] = true;
        // If pagePath ended with slash, do the same for actualPagePath.
        if (substr($pagePath, -1) == '/') $actualPagePath .= '/';
        $this->breadcrumbs[$pagePath]['actualPagePath'] = $actualPagePath;
        $this->breadcrumbs[$pagePath]['breadcrumbs'] = $breadcrumbs;
        return $this->breadcrumbs[$pagePath];
    }

    private function getCacheInfo($pagePath, $fileName, $revision)
    {
        // Get all cache{\d}Dimensions columns.
        $pageId = $this->pagePathToPageId($pagePath);
        if ($pageId === false) return array();
        $q = "SHOW COLUMNS FROM `files` LIKE 'cache%Dimensions'";
        $this->t->query($q);
        $numCaches = $this->t->num_rows;
        if ($numCaches == 0) return array();
        $cacheNDimensions = '';
        for ($i = 1; $i <= $numCaches; $i++)
            $cacheNDimensions .= "cache{$i}Dimensions,";
        $cacheNDimensions = substr($cacheNDimensions, 0, -1);
        $mFileName = $this->t->escapeString($fileName);
        $q = "
            SELECT $cacheNDimensions FROM `files`
            WHERE pageId = $pageId AND fileName = '$mFileName' AND revision = $revision
        ";
        $this->t->query($q);
        if ($this->t->num_rows != 1) return array();
        $r = $this->t->getNextRecord();
        $caches = array();
        for ($i = 1; $i <= $numCaches; $i++)
            $caches[$i] = $r["cache{$i}Dimensions"];
        return $caches;
    }

    private function getCurrentRevision($pagePath, $fileName)
    {
        // Get the specific revision we are acting upon.
        $pageId = $this->pagePathToPageId($pagePath);
        if ($pageId === false) return false;
        $mFileName = $this->t->escapeString($fileName);
        $revision = $this->getMaxRevision();
        if (hg('r') && is_numeric(gg('r')) && (intval(gg('r')) == gg('r')))
            $revision = gg('r');
        $q = "
            SELECT revision FROM (
                SELECT revision, mimeType FROM `files`
                WHERE pageId = $pageId AND fileName = '$mFileName' AND revision <= $revision
                ORDER BY revision DESC
                LIMIT 1
            ) AS files2 WHERE mimeType != 'deleted'
        ";
        $this->t->query($q);
        if ($this->t->num_rows == 0) return false;
        $r = $this->t->getNextRecord();
        return $r['revision'];
    }

    private function getEffectiveTheme($breadcrumbs)
    {
        $theme = 'default';
        $n = count($breadcrumbs);
        for ($i = $n - 1; $i >= 0; $i--)
        {
            if (isset($breadcrumbs[$i]['settings']['theme']))
            {
                $theme = $breadcrumbs[$i]['settings']['theme'];
                break;
            }
        }
        return $theme;
    }

    private function getMaxRevision()
    {
        $q = "
            SELECT GREATEST(maxPage, maxFiles) AS maxRevision
            FROM (
                (SELECT IFNULL(MAX(pages.revision), 0) AS maxPage FROM pages) AS m1,
                (SELECT IFNULL(MAX(`files`.revision), 0) AS maxFiles FROM `files`) AS m2
            )
        ";
        $this->t->query($q);
        $r = $this->t->getNextRecord();
        return $r['maxRevision'];
    }

    private function getThemeableTree($pageId)
    {
        // Get the highest revision as the baseline.
        $revision = $this->getMaxRevision();
        if (hg('r') && is_numeric(gg('r')) && (intval(gg('r')) == gg('r')))
            $revision = gg('r');
        // Get highest revision <= maxRevision for each page.
        $q = "
            SELECT pages1.id, parentId, userId, revision, pageName
            FROM pages AS pages1
            INNER JOIN (
                SELECT id, MAX(revision) AS maxRevision FROM pages
                WHERE revision <= $revision
                GROUP BY id
            ) AS pages2
            ON pages1.id = pages2.id
            AND pages1.revision = pages2.maxRevision
            AND pages1.kind != 'deleted'
        ";
        $this->t->query($q);
        $n = $this->t->num_rows;
        // Construct a pages array keyed by page ID.
        $pages = array();
        for ($i = 0; $i < $n; $i++)
        {
            $r = $this->t->getNextRecord();
            $pages[$r['id']] = $r;
        }
        if (!$this->isAdministrator())
        {
            // Can't alter theme on pages not owned.
            foreach ($pages as $id => $p)
                if (!$this->isOwner($p)) unset($pages[$id]);
        }
        // Construct a tree, noting that the keys of $pages must equal the
        // id of each page as assembled above.
        $tree = array();
        $treeIndex = array();
        while (count($pages) > 0)
        {
            foreach ($pages as $id => $page)
            {
                if ($page['parentId'] > 0)
                {
                    if (
                        !array_key_exists($page['parentId'], $pages)
                        && !array_key_exists($page['parentId'], $treeIndex)
                    ) {
                        // This is an orphan; unset/skip and continue.
                        unset($pages[$id]);
                        continue;
                    }
                    // Not an orphan.
                    if (array_key_exists($page['parentId'], $treeIndex))
                    {
                        $parent = &$treeIndex[$page['parentId']];
                        if (!isset($parent['children']))
                            $parent['children'] = array();
                        $parent['children'][$id] = $page;
                        $treeIndex[$id] = &$parent['children'][$id];
                        unset($pages[$id]);
                    }
                    continue;
                }
                // This is the top-most node; parentId = 0.
                $tree[$id] = $page;
                $treeIndex[$id] = &$tree[$id];
                unset($pages[$id]);
            }
        }
        $this->sortPageTree($tree);
        if (!isset($treeIndex[$pageId])) return false;
        $subTree = array($pageId => $treeIndex[$pageId]);
        return $subTree;
    }

    private function imageConstrain($mimeType, $contents, $cropBox)
    {
        $oldIm = new Imagick();
        $oldIm->readImageBlob($contents);
        $oldX = $oldIm->getImageWidth();
        $oldY = $oldIm->getImageHeight();
        // We will only perform the resizing if the given contents exceeds
        // the max bounding box.
        if (($oldX <= $cropBox) && ($oldY <= $cropBox))
        {
            $oldIm->clear();
            return array($contents, $oldX, $oldY);
        }
        // Go ahead and resize it.
        $px = $oldX / $cropBox;
        $py = $oldY / $cropBox;
        $p = $px > $py ? $px : $py;
        $newX = round($oldX / $p);
        $newY = round($oldY / $p);
        $contents = $this->imageResize($mimeType, $oldIm, $newX, $newY);
        $oldIm->clear();
        return array($contents, $newX, $newY);
    }

    private function imageResize($mimeType, $im, $newX, $newY)
    {
        if (($mimeType == 'image/jpeg') || ($mimeType == 'image/pjpeg'))
        {
            $im->resizeImage($newX, $newY, Imagick::FILTER_LANCZOS, 1);
            $im->setImageFormat('jpeg');
            $im->setImageCompressionQuality(100);
            return $im;
        }
        if ($mimeType == 'image/png')
        {
            $im->resizeImage($newX, $newY, Imagick::FILTER_LANCZOS, 1);
            $im->setImageFormat('png');
            return $im;
        }
        if ($mimeType == 'image/gif')
        {
            $im->setImageFormat('gif');
            if ($im->getNumberImages() == 1)
            {
                $im->resizeImage($newX, $newY, Imagick::FILTER_LANCZOS, 1);
                return $im;
            }
            // Resize animated GIF.
            $im = $im->coalesceImages();
            foreach ($im as $frame) {
                $frame->resizeImage($newX, $newY, Imagick::FILTER_LANCZOS, 1);
                $frame->thumbnailImage($newX, $newY);
                $frame->setImagePage($newX, $newY, 0, 0);
            }
            $im = $im->deconstructImages();
            return $im->getImagesBlob();
        }
        if ($mimeType == 'image/webp')
        {
            $im->resizeImage($newX, $newY, Imagick::FILTER_LANCZOS, 1);
            $im->setImageFormat('webp');
            $im->setImageCompressionQuality(100);
            return $im;
        }
    }

    private function imageToCache($numCaches, $pagePath, $fileName, $revision, $contents, $newX, $newY)
    {
        // Replace the oldest-read cached image with the new one and
        // return the new cache number.
        $pageId = $this->pagePathToPageId($pagePath);
        if ($pageId === false) return 0; // Not being cached.
        $cacheNRead = '';
        for ($i = 1; $i <= $numCaches; $i++)
            $cacheNRead .= "cache{$i}Read,";
        $cacheNRead = substr($cacheNRead, 0, -1);
        // Get the index of the oldest cache{\d}Read column.
        $mFileName = $this->t->escapeString($fileName);
        $q = "SELECT CASE\n";
        for ($i = 1; $i <= $numCaches; $i++)
            $q .= "WHEN LEAST($cacheNRead) = cache{$i}Read THEN '$i'\n";
        $q .= "END AS oldestRead\n";
        $q .= "FROM `files`\n";
        $q .= "WHERE pageId = $pageId AND fileName = '$mFileName' AND revision = $revision";
        $this->t->query($q);
        if ($this->t->num_rows != 1) return 0; // Not being cached.
        $r = $this->t->getNextRecord();
        $i = $r['oldestRead'];
        $dims = $newX . 'x' . $newY;
        $now = gmdate('Y-m-d H:i:s');
        if ($this->storageRoot !== false)
        {
            $mContents = '';
            $filePart = pathinfo($fileName, PATHINFO_FILENAME);
            $extPart = pathinfo($fileName, PATHINFO_EXTENSION);
            $this->storageSave($pagePath, $filePart . ".cache$i" . '.' . $extPart, $contents);
        }
        else
            $mContents = $this->t->escapeString($contents);
        unset($contents);
        $q = "
            UPDATE `files`
            SET cache{$i} = '$mContents', cache{$i}Dimensions = '$dims', cache{$i}Read = '$now'
            WHERE pageId = $pageId AND fileName = '$mFileName' AND revision = $revision
        ";
        $this->t->query($q);
        return $i;
    }

    private function incrementFileViews($fileId)
    {
        // Say a client's visit every 2 hours is new.
        $ip = getClientIp();
        if ($ip === false) return false;
        // Purge old client touch entries.
        $q = "DELETE FROM filesTouch WHERE UTC_TIMESTAMP() - INTERVAL 2 HOUR > touched";
        $this->t->query($q);
        // Is this a new visit?
        $now = gmdate('Y-m-d H:i:s');
        $q = "
            SELECT touched FROM filesTouch WHERE ipv4 = INET_ATON('$ip')
            AND fileId = $fileId AND UTC_TIMESTAMP - INTERVAL 2 HOUR <= touched
        ";
        $this->t->query($q);
        if ($this->t->num_rows == 0)
        {
            // This is a new visit; count it.
            $q = "
                INSERT INTO filesTouch (ipv4, fileId, touched)
                VALUES (INET_ATON('$ip'), $fileId, '$now')
            ";
            $this->t->query($q);
            $q = "UPDATE `files` SET views = views + 1 WHERE id = $fileId";
            $this->t->query($q);
        }
        else
        {
            // This is an old visit; don't count it.
            $q = "
                UPDATE filesTouch SET touched = '$now'
                WHERE ipv4 = INET_ATON('$ip') AND fileId = $fileId
            ";
            $this->t->query($q);
        }
    }

    private function pageTreeToPageList($tree)
    {
        $pages = array();
        foreach ($tree as $branch)
        {
            $page = $branch; unset($page['children']);
            $pages[$page['id']] = $page;
            if (isset($branch['children']))
            {
                $pages2 = $this->pageTreeToPageList($branch['children']);
                $pages = $pages + $pages2;
            }
        }
        return $pages;
    }

    private function parsePagePath($pagePath)
    {
        // Takes an absolute page path of the form "/a/b/c/" and returns
        // pieces, discarding leading/trailing empty pieces.
        if (substr($pagePath, 0, 1) != '/') return false;
        $parts = explode('/', $pagePath);
        // Remove leading/trailing empty strings.
        array_shift($parts);
        while ((count($parts) > 0) && (end($parts) == '')) array_pop($parts);
        return $parts;
    }

    private function sortPageTree(&$sitemap)
    {
        uasort($sitemap, array($this, 'sortPageTreeCompare'));
        foreach ($sitemap as &$node)
            if (isset($node['children']))
                $this->sortPageTree($node['children']);
    }

    private function sortPageTreeCompare($a, $b)
    {
        if (strtolower($a['pageName']) < strtolower($b['pageName']))
            return -1;
        return 1;
    }

    private function storageDelete($pagePath, $fileName)
    {
        $safeFileName = safeFileName($fileName);
        $target = $this->storageRoot . $pagePath . $safeFileName;
        // Delete master file.
        if (is_file($target)) unlink($target);
        // Delete any cached versions of it.
        $filePart = pathinfo($safeFileName, PATHINFO_FILENAME);
        $extPart = pathinfo($safeFileName, PATHINFO_EXTENSION);
        $cacheGlob = $this->storageRoot . $pagePath . $filePart . ".cache*" . '.' . $extPart;
        foreach (glob($cacheGlob) as $cacheFile)
            if (is_file($cacheFile)) unlink($cacheFile);
        // Delete any empty directories that might result.
        $delDir = $this->storageRoot . $pagePath;
        while ($delDir != $this->storageRoot)
        {
            if (@rmdir($delDir))
                $delDir = dirname($delDir);
            else
                break;
        }
    }

    private function storageDeleteTree($pagePath)
    {
        if ($this->storageRoot === false) return;
        $targetDir = $this->storageRoot . $pagePath;
        if (!is_dir($targetDir)) return;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($targetDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $f)
        {
            if ($f->isDir())
                rmdir($f->getRealPath());
            else
                unlink($f->getRealPath());
        }
        // Delete any empty directories that might result.
        $delDir = $this->storageRoot . $pagePath;
        while ($delDir != $this->storageRoot)
        {
            if (@rmdir($delDir))
                $delDir = dirname($delDir);
            else
                break;
        }
    }

    private function storageDump($pagePath, $fileName, $start, $end, $cacheLevel)
    {
        if ($cacheLevel > 0)
        {
            $filePart = pathinfo($fileName, PATHINFO_FILENAME);
            $extPart = pathinfo($fileName, PATHINFO_EXTENSION);
            $cacheFileName = $filePart . ".cache$cacheLevel" . '.' . $extPart;
            $target = $this->storageRoot . $pagePath . safeFileName($cacheFileName);
        }
        else
            $target = $this->storageRoot . $pagePath . safeFileName($fileName);
        if (!is_file($target)) return false;
        $fileSize = filesize($target);
        $mimeType = mime_content_type($target);
        $length = $fileSize;
        if ($fileSize == 0)
            $start = null;
        else if ($start !== null)
        {
            // Convert HTTP_RANGE values to Content-Range equivalents.
            if ($start < 0)
                $start = max(0, $fileSize + $start);
            else if ($end === null)
                $end = $fileSize - 1;
            $length = $end - $start + 1;
        }
        $this->dumpFileHeaders($fileName, $mimeType, $length, $start, $end, $fileSize);
        set_time_limit(0);
        $fp = fopen($target, 'rb');
        if ($start !== null)
            fseek($fp, $start);
        $buffer = 8192;
        if ($end === null) $end = $length - 1;
        while (!feof($fp) && ftell($fp) <= $end)
        {
            echo fread($fp, min($buffer, $end - ftell($fp) + 1));
            flush();
        }
        fclose($fp);
        exit();
    }

    private function storageGet($pagePath, $fileName)
    {
        $target = $this->storageRoot . $pagePath . safeFileName($fileName);
        if (!is_file($target)) return false;
        return file_get_contents($target);
    }

    private function storageSave($pagePath, $fileName, $contents)
    {
        $target = $this->storageRoot . $pagePath . safeFileName($fileName);
        if (!is_dir($this->storageRoot . $pagePath))
            mkdir($this->storageRoot . $pagePath, recursive: true);
        file_put_contents($target, $contents);
    }

    private function touchCache($pagePath, $fileName, $revision, $cacheLevel)
    {
        $pageId = $this->pagePathToPageId($pagePath);
        $mFileName = $this->t->escapeString($fileName);
        $now = gmdate('Y-m-d H:i:s');
        $q = "
            UPDATE `files` SET cache{$cacheLevel}Read = '$now'
            WHERE pageId = $pageId AND fileName = '$mFileName' AND revision = $revision
        ";
        $this->t->query($q);
    }
}
?>
