<?php
// Copyright 2017 Fleming Computer.
// All Rights Reserved.
?>
<?php
class Themes
{
    // Element attributes.

    // Form inputs.
    private $files;
    private $newTheme;

    private $id;
    private $cm;
    private $mode;

    function __construct()
    {
        $this->id = 'Themes_' . $GLOBALS['pizza']['pagePath'];
        $this->cm = $GLOBALS['pizza']['cm'];
    }

    function getHtml()
    {
        if (!isAdministrator()) error404();
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $this->initThis();
        if (!empty($_POST) && isset($_POST['formId'])
            && ($_POST['formId'] == $this->id)) $this->validateInput($_POST);

        // Show an error when the POST max size for PHP is exceeded.
        if (($_SERVER['REQUEST_METHOD'] == 'POST') && empty($_POST))
        {
            $maxPostSize = ini_get('post_max_size');
            $u = strtoupper(substr($maxPostSize, -1));
            if ($u == 'K') $maxPostSize = substr($maxPostSize, 0, -1) * 1024;
            else if ($u == 'M') $maxPostSize = substr($maxPostSize, 0, -1) * 1048576;
            else if ($u == 'G') $maxPostSize = substr($maxPostSize, 0, -1) * 1073741824;
            if ($_SERVER['CONTENT_LENGTH'] > $maxPostSize)
            {
                $this->files['value'] = '';
                $this->files['errors'] = array();
                $this->files['errors'][0] = 'total upload is too large ('
                    . ini_get('post_max_size') . ' max)';
                ss($this->id, $this);
            }
        }

        if (preg_match('/^copy-(.+)$/', $this->mode, $matches))
            $html = $this->showCopy($matches[1]);
        else if ($this->mode == "edit")
            $html = $this->showEdit();
        else if ($this->mode == 'list')
            $html = $this->showList();

        addJavaScript("$urlRoot/js/themes.js");
        ob_start();
        ?>
        <script type="text/javascript">
            $(document).ready(function() {
                $("#theme-editor").pizzaThemes();
            });
        </script>
        <?php
        $script = ob_get_clean();

        return $html . $script;
    }

    private function copyThemeFromDb($srcTheme, $newTheme)
    {
        $srcThemeUri = $this->cm->pageNameToPageUri($srcTheme);
        $newThemeUri = $this->cm->pageNameToPageUri($newTheme);
        if (!$this->cm->hasPage("/themes/$srcThemeUri/")) return false;
        if ($this->cm->hasPage("/themes/$newThemeUri/")) return false;
        // Create the new theme page.
        $result = $this->cm->addPage('/themes', $newTheme, '', 'theme', 'override');
        if ($result === false) return false;
        // Copy srcTheme's subpages.
        $subpages = $this->cm->getChildren("/themes/$srcThemeUri/", 'theme-file');
        foreach ($subpages as $sp)
        {
            $pageName = $sp['pageName'];
            $body = $sp['body'];
            $this->cm->addPage("/themes/$newThemeUri", $pageName, $body, 'theme-file');
        }
        // Copy srcTheme's files.
        $files = $this->cm->getFileInfo("/themes/$srcThemeUri/");
        foreach ($files as $f)
        {
            $fileName = $f['fileName'];
            $fileSize = $f['fileSize'];
            $mimeType = $f['mimeType'];
            $contents = $this->cm->getFileContents("/themes/$srcThemeUri/", $fileName);
            $this->cm->addFile("/themes/$newThemeUri/", $fileName, $fileSize, $mimeType, $contents);
        }
    }

    private function copyThemeFromFiles($srcTheme, $newTheme)
    {
        $docRoot = $GLOBALS['pizza']['docRoot'];
        $srcThemePath = "$docRoot/themes/$srcTheme";
        if (!is_dir($srcThemePath)) return false;
        $newThemeUri = $this->cm->pageNameToPageUri($newTheme);
        if ($this->cm->hasPage("/themes/$newThemeUri")) return false;
        // Don't copy if srcTheme has a subdirectory involved.
        $files = array_diff(scandir($srcThemePath), array('..', '.'));
        foreach ($files as $f)
            if (is_dir($f)) return false;
        // Create the new theme page.
        $result = $this->cm->addPage('/themes', $newTheme, '', 'theme', 'override');
        if ($result === false) return false;
        // Copy srcTheme's files into the database.
        foreach ($files as $fileName)
        {
            if ((substr($fileName, -5) == '.html')
                || (substr($fileName, -4) == '.css')
                || (substr($fileName, -3) == '.js'))
            {
                // Add these as (editable) subpages.
                $body = @file_get_contents("$srcThemePath/$fileName");
                $this->cm->addPage("/themes/$newThemeUri", $fileName, $body, 'theme-file');
            }
            else
            {
                // Add as file.
                $fileSize = filesize("$srcThemePath/$fileName");
                $mimeType = mime_content_type("$srcThemePath/$fileName");
                $contents = @file_get_contents("$srcThemePath/$fileName");
                $this->cm->addFile("/themes/$newThemeUri/", $fileName, $fileSize, $mimeType, $contents);
            }
        }
    }

    private function getThemesFromDb()
    {
        $themes = $this->cm->getChildren('/themes/', 'theme');
        $themesFromDb = array();
        foreach ($themes as $t)
            $themesFromDb[] = $t['pageName'];
        sort($themesFromDb);
        return $themesFromDb;
    }

    private function getThemesFromFiles()
    {
        $docRoot = $GLOBALS['pizza']['docRoot'];
        if (!is_dir("$docRoot/themes")) return array();
        $themesFromFiles = array();
        foreach (glob("$docRoot/themes/*", GLOB_ONLYDIR) as $d)
            $themesFromFiles[] = basename($d);
        sort($themesFromFiles);
        return $themesFromFiles;
    }

    private function initThis()
    {
        $o = sge($this->id, false);
        $this->files = $o ? $o->files : array('value' => '', 'errors' => array());
        $this->newTheme = $o ? $o->newTheme : array('value' => '', 'error' => '');
        $this->mode = $o ? $o->mode : 'list';
    }

    private function showCopy($srcTheme)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        ob_start();
        ?>
        <h1>Themes</h1>
        <div id="theme-editor">
            <form id="formThemes" action="<?=$urlRoot?><?=$pagePath?>" enctype="multipart/form-data" method="post" name="<?=myHtmlEntities($this->id)?>">
                <input name="formId" type="hidden" value="<?=myHtmlEntities($this->id)?>">
                <?php
                $class = $this->newTheme['error'] != '' ? 'class="error-placeholder"' : '';
                ?>
                <span>Copy “<?=myHtmlEntities($srcTheme)?>” to:</span><br>
                <input <?=$class?> name="newTheme" placeholder="New Name" type="text" value="<?=myHtmlEntities($this->newTheme['value'])?>">
                <input class="submit" name="saveCopy" type="submit" value="Save">
                <input class="submit" name="cancelCopy" type="submit" value="Cancel">
                <?php
                if ($this->newTheme['error'] != '')
                {
                    ?>
                    <div class="error">&nbsp;&nbsp;↑ <?=myHtmlEntities($this->newTheme['error'])?> ↑</div>
                    <?php
                }
                ?>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function showEdit()
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $previewTheme = sge('theme', false);
        ob_start();
        ?>
        <h1>Themes</h1>
        <div id="theme-editor">
            <form id="formThemes" action="<?=$urlRoot?><?=$pagePath?>" enctype="multipart/form-data" method="post" name="<?=myHtmlEntities($this->id)?>">
                <input name="formId" type="hidden" value="<?=myHtmlEntities($this->id)?>">
                <?php
                $previewUri = $this->cm->pageNameToPageUri($previewTheme);
                $previewPagePath = "/themes/$previewUri/";
                $subpages = $this->cm->getChildren($previewPagePath, 'theme-file');
                $themeFiles = array(
                    'theme.css' => '',
                    'theme.html' => '',
                    'theme.js' => ''
                );
                foreach ($subpages as $sp)
                {
                    $pageName = $sp['pageName'];
                    $body = $sp['body'];
                    if (($pageName == 'theme.css') || ($pageName == 'theme.html') || ($pageName == 'theme.js'))
                       $themeFiles[$pageName] = $body;
                }
                ?>
                <span style="margin-right: 20px;">You are previewing &amp; editing “<?=myHtmlEntities($previewTheme)?>”.</span>
                <div style="display: inline-block; white-space: nowrap;">
                <input class="submit" name="save[<?=myHtmlEntities($previewTheme)?>]" type="submit" value="Save">
                <input class="submit" name="done" type="submit" value="Done">
                </div>
                <div id="tabs">
                <ul>
                    <?php
                    foreach ($themeFiles as $fileName => $body)
                    {
                        $name = myHtmlEntities(str_replace('.', '_', $fileName));
                        ?>
                        <li><a href="#tab-<?=$name?>"><?=myHtmlEntities($fileName)?></a></li>
                        <?php
                    }
                    ?>
                    <li><a href="#tab-files">Files</a></li>
                </ul>
                <?php
                foreach ($themeFiles as $fileName => $body)
                {
                    $name = myHtmlEntities(str_replace('.', '_', $fileName));
                    ?>
                    <div id="tab-<?=$name?>">
                    <textarea name="<?=$name?>" style="height: 30em; font-family: Courier, 'Courier New', 'Andale Mono', 'Lucida Console', Monaco; font-size: medium;"><?=myHtmlEntities($body)?></textarea>
                    </div>
                    <?php
                }
                ?>

                <div id="tab-files" style="margin-top: 0.5em;">
                <div data-filer="hide">
                    <?php
                    $fileInfo = $this->cm->getFileInfo($previewPagePath);
                    if ($fileInfo === false) echo '<br>';
                    else
                    {
                        ?>
                        <div class="attached-files">
                        With checked: <input name="deleteSelected" type="submit" value="Delete"><br>
                        <?php
                        foreach ($fileInfo as $f)
                        {
                            $fileName = $f['fileName'];
                            $fileSize = $f['fileSize'];
                            $mFileName = myHtmlEntities($fileName);
                            if ($fileSize < 1000) $fileSize .= 'B';
                            else if ($fileSize < 1000000) $fileSize = number_format($fileSize / 1000) . 'K';
                            else $fileSize = number_format($fileSize / 1000000, 1) . 'M';
                            $imgUrl = "$urlRoot/images/mime/generic.gif";
                            $fileDetails = "$fileSize";
                            if (($f['mimeType'] == 'image/jpeg')
                                || ($f['mimeType'] == 'image/pjpeg')
                                || ($f['mimeType'] == 'image/gif')
                                || ($f['mimeType'] == 'image/png')
                                || ($f['mimeType'] == 'image/svg+xml')
                                || ($f['mimeType'] == 'image/webp'))
                            {
                                if ($f['dimensions'] != '')
                                {
                                    list($x, $y) = explode('x', $f['dimensions']);
                                    $fileDetails = "$fileSize, {$x}x{$y}";
                                }
                                else
                                {
                                    $fileDetails = "$fileSize";
                                }
                                $imgUrl = $urlRoot . $previewPagePath . $mFileName . '?width=72&amp;height=24';
                            }
                            else if (substr($f['mimeType'], -3, 3) == 'pdf')
                                $imgUrl = "$urlRoot/images/mime/pdf.gif";
                            else if (substr($f['mimeType'], 0, 5) == 'audio')
                                $imgUrl = "$urlRoot/images/mime/audio.gif";
                            ?>
                            <input name="attachedFiles[]" type="checkbox" value="<?=$mFileName?>">
                            <div style="display: inline-block; text-align: center; width: 72px;">
                                <img src="<?=$imgUrl?>" border="0" alt="<?=$mFileName?>" style="height: 24px; max-width: 72px;">
                            </div>
                            <a href="<?=$urlRoot?><?=$previewPagePath?><?=$mFileName?>" target="_blank"><?=$mFileName?></a> (<?=$fileDetails?>)<br>
                            <?php
                        }
                        ?>
                        </div>
                        <?php
                    }
                    $maxFileSize = ini_get('upload_max_filesize');
                    $u = strtoupper(substr($maxFileSize, -1));
                    if ($u == 'K') $maxFileSize = substr($maxFileSize, 0, -1) * 1024;
                    else if ($u == 'M') $maxFileSize = substr($maxFileSize, 0, -1) * 1048576;
                    else if ($u == 'G') $maxFileSize = substr($maxFileSize, 0, -1) * 1073741824;
                    ?>
                    <input name="MAX_FILE_SIZE" type="hidden" value="<?=$maxFileSize?>">
                    <input name="files[]" type="file" multiple="multiple">
                    <input name="upload" type="submit" value="Upload">
                    <?php
                    if (count($this->files['errors']) > 0)
                    {
                        $errors = $this->files['errors'];
                        foreach ($errors as $i => $e)
                        {
                            $fileName = '';
                            if (isset($this->files['value']['name'][$i]))
                                $fileName = $this->files['value']['name'][$i] . ': ';
                            ?>
                            <div class="error">↑<?=myHtmlEntities($fileName)?><?=myHtmlEntities($e)?>↑</div>
                            <?php
                            unset($this->files['errors'][$i]);
                            ss($this->id, $this);
                        }
                    }
                    ?>
                </div>
                <?php
                addJavaScript("$urlRoot/js/filer.js");
                ?>
                <script type="text/javascript">
                    $(document).ready(function() {
                        $("#filer").pizzaFiler({
                            handler: '<?="$urlRoot/ajax/filer-handler"?>',
                            path: "<?=$previewPagePath?>",
                            maxFileSize: <?=$maxFileSize?>
                        });
                    });
                </script>
                <input id="filer" name="filer[]" type="file" multiple="multiple">
                </div>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function showList()
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $themesFromDb = $this->getThemesFromDb();
        $themesFromFiles = $this->getThemesFromFiles();
        $previewTheme = sge('theme', false);
        $themesInUse = $this->cm->getThemesInUse();
        ob_start();
        ?>
        <h1>Themes</h1>
        <div id="theme-editor">
            <form id="formThemes" action="<?=$urlRoot?><?=$pagePath?>" enctype="multipart/form-data" method="post" name="<?=myHtmlEntities($this->id)?>">
                <input name="formId" type="hidden" value="<?=myHtmlEntities($this->id)?>">
                <div id="theme-list">
                    <?php
                    if ($previewTheme !== false)
                    {
                        ?>
                        <span>You are previewing “<?=myHtmlEntities($previewTheme)?>”.</span>
                        <?php
                    }
                    if (empty($themesFromDb))
                    {
                        ?>
                        <p>There are no user-defined themes.</p>
                        <?php
                    }
                    foreach ($themesFromDb as $theme)
                    {
                        if (isset($themesInUse[$theme])) $editLabel = 'Edit Live';
                        else $editLabel = 'Edit';
                        ?>
                        <p>
                            <?=myHtmlEntities($theme)?>
                            <?php
                            if ($theme == $previewTheme)
                            {
                                ?>
                                <input class="submit" name="edit[<?=myHtmlEntities($theme)?>]" type="submit" value="<?=$editLabel?>">
                                <input class="submit" name="copy[<?=myHtmlEntities($theme)?>]" type="submit" value="Edit Copy">
                                <input class="submit" name="endPreview" type="submit" value="End Preview">
                                <?php
                            }
                            else
                            {
                                ?>
                                <input class="submit" name="preview[<?=myHtmlEntities($theme)?>]" type="submit" value="Preview">
                                <?php
                                if (($previewTheme === false) && isset($themesInUse[$theme]))
                                {
                                    $using = '';
                                    foreach ($themesInUse[$theme] as $t) $using .= '"' . $t . '"' . ', ';
                                    $using = substr($using, 0, -2);
                                    ?>
                                    <span style="font-size: smaller;">used by <?=$using?></span>
                                    <?php
                                }
                                else if ($previewTheme === false)
                                {
                                    ?>
                                    <input class="submit" name="delete[<?=myHtmlEntities($theme)?>]" type="submit" value="Delete">
                                    <?php
                                }
                            }
                            ?>
                        </p>
                        <?php
                    }
                    ?>
                    <h2>Built-In Themes</h2>
                    <?php
                    if (empty($themesFromFiles))
                    {
                        ?>
                        <p>There are no built-in themes.</p>
                        <?php
                    }
                    foreach ($themesFromFiles as $theme)
                    {
                        ?>
                        <p>
                            <?=myHtmlEntities($theme)?>
                            <?php
                            if ($theme == $previewTheme)
                            {
                                ?>
                                <input class="submit" name="copy[<?=myHtmlEntities($theme)?>]" type="submit" value="Edit Copy">
                                <input class="submit" name="endPreview" type="submit" value="End Preview">
                                <?php
                            }
                            else
                            {
                                ?>
                                <input class="submit" name="preview[<?=myHtmlEntities($theme)?>]" type="submit" value="Preview">
                                <?php
                                if (($previewTheme === false) && isset($themesInUse[$theme]))
                                {
                                    $using = '';
                                    foreach ($themesInUse[$theme] as $t) $using .= '"' . $t . '"' . ', ';
                                    $using = substr($using, 0, -2);
                                    ?>
                                    <span style="font-size: smaller;">used by <?=$using?></span>
                                    <?php
                                }
                            }
                            ?>
                        </p>
                        <?php
                    }
                    ?>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function updateThemeFile($themeUri, $fileName, $body)
    {
        // If nonempty, add if new or edit if old.  If empty, delete it.
        if ($body != '')
        {
            if (substr($body, -1, 1) != "\n") $body .= "\n";
            if ($this->cm->hasPage("/themes/$themeUri/$fileName"))
                $this->cm->editPage("/themes/$themeUri/$fileName", $body);
            else
                $this->cm->addPage("/themes/$themeUri", $fileName, $body, 'theme-file');
        }
        else
            $this->cm->deletePage("/themes/$themeUri/$fileName", false);
    }

    private function validateInput($input)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $v = $GLOBALS['pizza']['v'];
        $v->setMethod('post');
        $v->reset();

        if (preg_match('/^copy-(.+)$/', $this->mode, $matches))
        {
            $srcTheme = $matches[1];

            if ($v->submitted('cancelCopy'))
            {
                sc($this->id);
                relocateNow($urlRoot . currentPath());
            }

            if ($v->submitted('saveCopy'))
            {
                $this->newTheme = $v->checkLength('newTheme', 2, 40, 'normalize');
                if ($v->error)
                {
                    ss($this->id, $this);
                    sleep(3);
                    relocateNow($urlRoot . currentPath());
                }
                $newTheme = $this->newTheme['value'];
                // If themeUri starts and ends with '-', e.g. '-hello-', reserved.
                $themeUri = $this->cm->pageNameToPageUri($newTheme);
                if (preg_match('/^-.*-$/', $themeUri))
                {
                    $this->newTheme['error'] = 'reserved';
                    return;
                }
                $themesFromDb = $this->getThemesFromDb();
                $themesFromFiles = $this->getThemesFromFiles();
                if ((array_search($newTheme, $themesFromDb) !== false)
                    || (array_search($newTheme, $themesFromFiles) !== false))
                {
                    $this->newTheme['error'] = 'theme already exists';
                    ss($this->id, $this);
                    sleep(3);
                    relocateNow($urlRoot . currentPath());
                }
                if ((array_search($srcTheme, $themesFromDb) !== false))
                {
                    $this->copyThemeFromDb($srcTheme, $newTheme);
                }
                else if ((array_search($srcTheme, $themesFromFiles) !== false))
                {
                    $this->copyThemeFromFiles($srcTheme, $newTheme);
                }
                // Go ahead and preview/edit the new theme.
                ss('theme', $newTheme);
                sc($this->id);
                $this->initThis();
                $this->mode = 'edit';
                ss($this->id, $this);
                relocateNow($urlRoot . currentPath());
            }
        }

        if ($this->mode == 'edit')
        {
            if ($v->submitted('deleteSelected')
                || ($v->submitted('upload')))
            {
                $previewTheme = sge('theme', false);
                if ($previewTheme === false)
                {
                    sleep(3);
                    relocateNow($urlRoot . currentPath());
                }
                $previewUri = $this->cm->pageNameToPageUri($previewTheme);
                $previewPagePath = "/themes/$previewUri";
                if (!$this->cm->hasPage($previewPagePath))
                {
                    sleep(3);
                    relocateNow($urlRoot . currentPath());
                }
                // Delete files whose checkboxes were set.
                if ($v->submitted('deleteSelected'))
                {
                    $deleteFileNames = isset($input['attachedFiles']) && is_array($input['attachedFiles'])
                    ? $input['attachedFiles'] : array();
                    foreach ($deleteFileNames as $f) $this->cm->deleteFile($previewPagePath, $f);
                    if (!empty($deleteFileNames)) $fileChangesMade = true;
                }
                // See if files are to be uploaded.
                if (isset($_FILES['files']))
                {
                    $this->files['value'] = $_FILES['files'];
                    $fileErrorOccurred = false;
                    $fileCount = count($_FILES['files']['name']);
                    for ($i = 0; $i < $fileCount; $i++)
                    {
                        if ($_FILES['files']['name'][$i] == '') continue;
                        $fv = $v->checkUploadedFile('files', $i);
                        if ($v->error)
                        {
                            $this->files['errors'][$i] = $fv['error'];
                            $fileErrorOccurred = true;
                            $v->reset();
                            continue;
                        }
                        $f = $this->files['value'];
                        $fileName = $f['name'][$i];
                        // Make sure proposed file name doesn't collide with a page name.
                        if ($this->cm->hasPage($previewPagePath . $fileName))
                        {
                            $this->files['errors'][$i] = 'already exists';
                            $fileErrorOccurred = true;
                            continue;
                        }
                        $fileSize = $f['size'][$i];
                        $mimeType = $f['type'][$i];
                        $contents = file_get_contents($f['tmp_name'][$i]);
                        $this->cm->addFile($previewPagePath, $fileName, $fileSize, $mimeType, $contents);
                        $fileChangesMade = true;
                    }
                    if ($fileErrorOccurred)
                    {
                        ss($this->id, $this);
                        relocateNow($urlRoot . currentPath());
                    }
                }
                relocateNow($urlRoot . currentPath());
            }

            if ($v->submitted('done'))
            {
                $this->mode = 'list';
                ss($this->id, $this);
                relocateNow($urlRoot . currentPath());
            }

            if ($v->submitted('save'))
            {
                $themes = array_keys(pg('save'));
                $editTheme = array_pop($themes);
                $themesFromDb = $this->getThemesFromDb();
                if (array_search($editTheme, $themesFromDb) === false)
                {
                    sleep(3);
                    relocateNow($urlRoot . currentPath());
                }
                $editThemeUri = $this->cm->pageNameToPageUri($editTheme);
                $css = $v->checkLength('theme_css', 0, 1000000);
                $html = $v->checkLength('theme_html', 0, 1000000);
                $js = $v->checkLength('theme_js', 0, 1000000);
                if ($v->error)
                {
                    ss($this->id, $this);
                    sleep(3);
                    relocateNow($urlRoot . currentPath());
                }
                $this->updateThemeFile($editThemeUri, "theme.css", $css['value']);
                $this->updateThemeFile($editThemeUri, "theme.html", $html['value']);
                $this->updateThemeFile($editThemeUri, "theme.js", $js['value']);
                relocateNow($urlRoot . currentPath());
            }
        }

        if ($this->mode == 'list')
        {
            if ($v->submitted('copy'))
            {
                $themes = array_keys(pg('copy'));
                $srcTheme = array_pop($themes);
                $themesFromDb = $this->getThemesFromDb();
                $themesFromFiles = $this->getThemesFromFiles();
                if ((array_search($srcTheme, $themesFromDb) !== false)
                    || (array_search($srcTheme, $themesFromFiles) !== false))
                {
                    $this->mode = "copy-$srcTheme";
                    ss($this->id, $this);
                    relocateNow($urlRoot . currentPath());
                }
                relocateNow($urlRoot . currentPath());
            }

            if ($v->submitted('delete'))
            {
                $themes = array_keys(pg('delete'));
                $theme = array_pop($themes);
                // Don't delete if in use.
                $themesInUse = $this->cm->getThemesInUse();
                if (isset($themesInUse[$theme]))
                    relocateNow($urlRoot . currentPath());
                $themeUri = $this->cm->pageNameToPageUri($theme);
                $this->cm->deleteTree("/themes/$themeUri");
                sc($this->id);
                relocateNow($urlRoot . currentPath());
            }

            if ($v->submitted('edit'))
            {
                $themes = array_keys(pg('edit'));
                $newTheme = array_pop($themes);
                ss('theme', $newTheme);
                $this->mode = 'edit';
                ss($this->id, $this);
                relocateNow($urlRoot . currentPath());
            }

            if ($v->submitted('endPreview'))
            {
                sc('theme');
                sc($this->id);
                relocateNow($urlRoot . currentPath());
            }

            if ($v->submitted('preview'))
            {
                $themes = array_keys(pg('preview'));
                $newTheme = array_pop($themes);
                ss('theme', $newTheme);
                $this->mode = 'list';
                ss($this->id, $this);
                relocateNow($urlRoot . currentPath());
            }
        }

        sleep(3);
        relocateNow($urlRoot . currentPath());
    }
}
?>
