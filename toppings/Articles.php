<?php
// Copyright 2016 Fleming Computer.
// All Rights Reserved.
?>
<?php
class Articles
{
    // Element attributes.
    public $showDate;
    public $sortBy;
    public $sortDirection;

    // Form inputs for when editing an 'article' page.
    private $articleAuthor;
    private $articleDate;
    private $articleSource;
    private $articleUrl;
    private $attachedFiles;
    private $body;
    private $files;
    private $title;

    private $cm;
    private $editing;
    private $id;
    private $preview;
    private $tempPagePath;
    private $tp;

    function __construct()
    {
        $this->cm = $GLOBALS['pizza']['cm'];
        $this->tp = $GLOBALS['pizza']['toppings'];
    }

    function applyTopping($html)
    {
        // If this page is not an 'article', then only apply any
        // '[articles]' element and return.
        if ($GLOBALS['pizza']['page']['kind'] != 'article')
        {
            return preg_replace_callback(
                '/\[(articles)(]|\s+.*]|,.*])/sU',
                array($this, '_applyTopping'),
                $html
            );
        }

        // This is an 'article' page.
        $this->id = 'Articles_' . $GLOBALS['pizza']['pagePath'];
        $this->initThis();

        if (($_SERVER['REQUEST_METHOD'] ?? '') == 'POST')
        {
            // A form was submitted.
            if (empty($_POST))
            {
                // post_max_size was likely exceeded.
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
            else if (isset($_POST['formId']) && ($_POST['formId'] == $this->id))
                $this->validateInput($_POST);
            return $this->tp->protect($this->showForm());
        }

        if (hg('editPage') || $this->editing)
        {
            $this->editing = true;
            ss($this->id, $this);
            return $this->tp->protect($this->showForm());
        }

        return $this->showArticle($html);
    }

    private function _applyTopping($matches)
    {
        $e = substr($matches[0], 1, -1);
        $this->showDate = $this->tp->getString($e, 'date', 'on'); // on or off
        $this->sortBy = $this->tp->getString($e, 'sort', 'date'); // date or title
        $this->sortDirection = $this->tp->getString($e, 'direction', ''); // up or down
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $this->id = "Articles_$pagePath";
        $o = sge($this->id, false);
        $this->tempPagePath = $o ? $o->tempPagePath : '';

        if (isWritable() &&
            (hg('addArticle') || $this->cm->hasPage($this->tempPagePath)))
        {
            // Either adding just now or started earlier and need to finish.
            if (!$this->cm->hasPage($this->tempPagePath))
            {
                $this->tempPagePath = $this->cm->addTempPage($pagePath, 'article', 'y');
                ss($this->id, $this);
            }
            relocateNow($urlRoot . $this->tempPagePath . '?editPage');
        }

        return $this->tp->protect($this->showArticleList());
    }

    private function initThis()
    {
        $settings = $GLOBALS['pizza']['page']['settings'];
        $o = sge($this->id, false);
        $this->articleAuthor = $o ? $o->articleAuthor : array(
            'value' => isset($settings['articleAuthor']) ? $settings['articleAuthor'] : '',
            'error' => ''
        );
        $this->articleDate = $o ? $o->articleDate : array(
            'value' => substr($GLOBALS['pizza']['page']['created'], 0, 10),
            'error' => ''
        );
        $this->articleSource = $o ? $o->articleSource : array(
            'value' => isset($settings['articleSource']) ? $settings['articleSource'] : '',
            'error' => ''
        );
        $this->articleUrl = $o ? $o->articleUrl : array(
            'value' => isset($settings['articleUrl']) ? $settings['articleUrl'] : '',
            'error' => ''
        );
        $this->attachedFiles = $o ? $o->attachedFiles : array('value' => '', 'error' => '');
        $this->body = $o ? $o->body : array(
            'value' => $GLOBALS['pizza']['page']['body'],
            'error' => ''
        );
        $this->files = $o ? $o->files : array('value' => '', 'errors' => array());
        $this->title = $o ? $o->title : array(
            'value' => !$this->cm->isTempPage($GLOBALS['pizza']['pagePath'])
                ? $GLOBALS['pizza']['page']['pageName'] : '',
            'error' => ''
        );
        $this->editing = $o ? $o->editing : false;
        $this->preview = $o ? $o->preview : false;
    }

    private function showArticle($html)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        // Get parent's [article] options.
        $parentPagePath = $this->cm->parentPath($pagePath);
        $pp = $this->cm->getPage($parentPagePath);
        preg_match('/\[(articles)(]|\s+.*]|,.*])/sU', $pp['body'], $matches);
        $e = substr($matches[0], 1, -1);
        $showDate = $this->tp->getString($e, 'date', 'on');
        // Augment the display of the article with author, source, and url.
        ob_start();
        $created = $GLOBALS['pizza']['page']['created'];
        $date = '';
        if ($showDate != 'off')
            $date = date('M j, Y', strtotime($created . ' UTC'));
        $mTitle = myHtmlEntities($this->title['value']);
        $mArticleAuthor = $this->articleAuthor['value'] != ''
            ? "<br>\nBy " . myHtmlEntities($this->articleAuthor['value'])
            : '';
        $mArticleSource = $this->articleSource['value'] != ''
            ? "<br>\n" . myHtmlEntities($this->articleSource['value'])
            : '';
        $mArticleUrl = $this->articleUrl['value'] != ''
            ? "<br>\n" . "Original URL: <a href=\""
                . myHtmlEntities($this->articleUrl['value'])
                . "\" target=\"_blank\">"
                . myHtmlEntities($this->articleUrl['value'])
                . '</a>'
            : '';
        echo <<<P
<h1>$mTitle</h1>
<p style="font-size: smaller;">
{$date}{$mArticleAuthor}{$mArticleSource}{$mArticleUrl}
</p>
P;
        return $this->tp->protect(ob_get_clean()) . "\n\n" . trim($html);
    }

    private function showArticleList()
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $settings = $GLOBALS['pizza']['settings'];
        ob_start();
        $articles = $this->cm->getChildren($pagePath, 'article');
        $key = 'created';
        if ($this->sortBy == 'date') $key = 'created';
        else if ($this->sortBy == 'title') $key = 'pageName';
        $direction = -1;
        if ($this->sortDirection == 'up') $direction = -1;
        else if ($this->sortDirection == 'down') $direction = 1;
        else
        {
            // direction not given, so affected by sortBy
            if ($this->sortBy == 'title') $direction = 1;
        }
        // Sort.
        uasort($articles,
            function ($a, $b) use($key, $direction)
            {
                if ($a[$key] < $b[$key]) return -$direction;
                if ($a[$key] > $b[$key]) return $direction;
                return 0;
            }
        );
        if (count($articles) == 0)
        {
            echo <<<P
<p>There are no entries at this time.</p>
P;
            if (isWritable())
            {
                echo <<<P

<p><a href="$urlRoot$pagePath?addArticle">Add an Article</a></p>
P;
            }
            return ob_get_clean();
        }
        if (isWritable())
            echo <<<P
<p><a href="$urlRoot$pagePath?addArticle">Add an Article</a></p>

P;
        foreach ($articles as $a)
        {
            $pageName = $a['pageName'];
            $pageUri = $a['pageUri'];
            $created = $a['created'];
            $articleSource = $settings->get("{$pagePath}{$pageUri}/", 'articleSource', '');
            $articleAuthor = $settings->get("{$pagePath}{$pageUri}/", 'articleAuthor', '');
            // $articleUrl = $settings->get("{$pagePath}{$pageUri}/", 'articleUrl');
            $date = '';
            if ($this->showDate != 'off')
                $date = date('M j, Y', strtotime($created . ' UTC'));
            $mPageName = myHtmlEntities($pageName);
            $mArticleAuthor = $articleAuthor != '' ? ' - ' . myHtmlEntities($articleAuthor) : '';
            $mArticleSource = $articleSource != '' ? ' - ' . myHtmlEntities($articleSource) : '';
            $mode = '';
            if (substr($a['mode'], 0, 1) == 'n') $mode = ' <span style="color: red;">OFF</span>';
            echo <<<P
<p>
<a href="$urlRoot$pagePath$pageUri/">$mPageName</a>$mode<br>
<span style="font-size: smaller;">$date$mArticleAuthor$mArticleSource</span>
</p>

P;
        }
        return rtrim(ob_get_clean());
    }

    private function showForm()
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        ob_start();
        ?>
        <div class="page-editor" id="page-editor">
            <form action="<?=$urlRoot?><?=$pagePath?>" enctype="multipart/form-data" method="post" name="<?=myHtmlEntities($this->id)?>">
                <input name="formId" type="hidden" value="<?=myHtmlEntities($this->id)?>" />
                <?php
                if ($this->preview)
                {
                    $body = $this->tp->applyToppings($this->body['value'], get_class($this));
                    // $body = $this->tp->deactivateForms($body);
                    ?>
                    <div class="preview">
                        <h1><?=myHtmlEntities($this->title['value'])?></h1>
                        <div><?=$body?></div>

                        <hr style="clear: both;" />
                    </div>
                    <?php
                    $this->preview = false;
                }
                ?>

                <div data-filer="hide">
                    <?php
                    $fileInfo = $this->cm->getFileInfo($pagePath);
                    ?>
                    <img src="<?=$urlRoot?>/images/paperclip.gif" border="0" width="15" height="15" alt="paperclip" />
                    Attached Files:
                    <?php
                    if ($fileInfo === false) echo '<br>';
                    else
                    {
                        ?>
                        <input name="deleteSelected" type="submit" value="Delete Selected" />
                        <div class="attached-files">
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
                            if (
                                ($f['mimeType'] == 'image/jpeg')
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
                                $imgUrl = $urlRoot . $pagePath . $mFileName . '?width=48&amp;height=16';
                            }
                            else if (substr($f['mimeType'], -3, 3) == 'pdf')
                                $imgUrl = "$urlRoot/images/mime/pdf.gif";
                            else if (substr($f['mimeType'], 0, 5) == 'audio')
                                $imgUrl = "$urlRoot/images/mime/audio.gif";
                            ?>
                            <input name="attachedFiles[]" type="checkbox" value="<?=$mFileName?>" />
                            <img src="<?=$imgUrl?>" border="0" alt="<?=$mFileName?>" />
                            <a href="<?=$urlRoot?><?=$pagePath?><?=$mFileName?>"><?=$mFileName?></a> (<?=$fileDetails?>)<br>
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
                    <input name="MAX_FILE_SIZE" type="hidden" value="<?=$maxFileSize?>" />
                    <input name="files[]" type="file" multiple="multiple" />
                    <input name="upload" type="submit" value="Upload" />
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
                            maxFileSize: <?=$maxFileSize?>
                        });
                    });
                </script>
                <input id="filer" name="filer[]" type="file" multiple="multiple">

                <div>
                    <?php
                    $class = $this->title['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    <input <?=$class?> name="title" placeholder="Title" type="text" value="<?=myHtmlEntities($this->title['value'])?>" />
                    <?php
                    if ($this->title['error'] != '')
                    {
                        ?>
                        <span class="error">←<?=myHtmlEntities($this->title['error'])?></span>
                        <?php
                    }
                    ?>
                </div>

                <div>
                    <?php
                    $class = $this->articleDate['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    <input <?=$class?> name="articleDate" placeholder="Date" type="text" value="<?=myHtmlEntities($this->articleDate['value'])?>" />
                    <?php
                    if ($this->articleDate['error'] != '')
                    {
                        ?>
                        <span class="error">←<?=myHtmlEntities($this->articleDate['error'])?></span>
                        <?php
                    }
                    ?>
                </div>

                <div>
                    <?php
                    $class = $this->articleSource['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    <input <?=$class?> name="articleSource" placeholder="Source (optional)" type="text" value="<?=myHtmlEntities($this->articleSource['value'])?>" />
                    <?php
                    if ($this->articleSource['error'] != '')
                    {
                        ?>
                        <span class="error">←<?=myHtmlEntities($this->articleSource['error'])?></span>
                        <?php
                    }
                    ?>
                </div>

                <div>
                    <?php
                    $class = $this->articleAuthor['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    <input <?=$class?> name="articleAuthor" placeholder="Author (optional)" type="text" value="<?=myHtmlEntities($this->articleAuthor['value'])?>" />
                    <?php
                    if ($this->articleAuthor['error'] != '')
                    {
                        ?>
                        <span class="error">←<?=myHtmlEntities($this->articleAuthor['error'])?></span>
                        <?php
                    }
                    ?>
                </div>

                <div>
                    <?php
                    $class = $this->articleUrl['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    <input <?=$class?> name="articleUrl" placeholder="Original URL (optional)" type="text" value="<?=myHtmlEntities($this->articleUrl['value'])?>" />
                    <?php
                    if ($this->articleUrl['error'] != '')
                    {
                        ?>
                        <span class="error">←<?=myHtmlEntities($this->articleUrl['error'])?></span>
                        <?php
                    }
                    ?>
                </div>

                <textarea name="body" placeholder="Body"><?=myHtmlEntities($this->body['value'])?></textarea>
                <?php
                if ($this->body['error'] != '')
                {
                    ?>
                    <div class="error" style="clear: both;">↑<?=myHtmlEntities($this->body['error'])?>↑</div>
                    <?php
                }
                ?>

                <div>
                    <input class="submit" name="save" type="submit" value="Save" />
                    <input class="submit" name="preview" type="submit" value="Preview" />
                    <input class="submit" name="cancel" type="submit" value="Cancel" />
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function validateInput($input)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $v = $GLOBALS['pizza']['v'];
        $v->setMethod('post');
        $v->reset();
        $pagePath = currentPath();
        $parentPagePath = $this->cm->parentPath($pagePath);

        if ($v->submitted('cancel'))
        {
            // If temp page, delete it.
            if ($this->cm->isTempPage($pagePath))
            {
                $this->cm->deletePage($pagePath);
                // Also clear the parent's state (i.e. tempPagePath)
                sc('Articles_' . $parentPagePath);
            }
            sc($this->id);
            relocateNow($urlRoot . $parentPagePath);
        }

        if (
            $v->submitted('deleteSelected')
            || $v->submitted('upload')
            || $v->submitted('preview')
            || $v->submitted('save')
        ) {
            if ($this->cm->isReservedPage($pagePath)) relocateNow($urlRoot . currentUri());
            $this->title = $v->checkLength('title', 1, 200);
            if (!$v->error)
            {
                // Title's syntax is correct.  If the proposed title is
                // different from the current title, then check to see if it
                // is already used.
                $currentPageName = $GLOBALS['pizza']['page']['pageName'];
                $newPageName = $this->title['value'];
                $newPageUri = $this->cm->pageNameToPageUri($newPageName);
                // If newPageUri starts and ends with '-', e.g. '-hello-', reserved.
                if (preg_match('/^-.*-$/', $newPageUri))
                {
                    $this->title['error'] = 'reserved';
                    $v->error = true;
                }
                $newPagePath = $this->cm->parentPath($pagePath) . $newPageUri . '/';
                if (($currentPageName != $newPageName) && $this->cm->hasPage($newPagePath))
                {
                    $this->title['error'] = 'title already used';
                    $v->error = true;
                }
            }
            $this->articleAuthor = $v->checkLength('articleAuthor', 0, 50);
            $this->articleDate = $v->checkDate('articleDate');
            $this->articleSource = $v->checkLength('articleSource', 0, 100);
            $this->articleUrl = $v->checkUrl('articleUrl', 0, 250);
            $this->body = $v->checkLength('body', 2, 30000);
            if ($v->error)
            {
                ss($this->id, $this);
                return;
            }
            $fileChangesMade = false;
            // Delete files whose checkboxes were set.
            if ($v->submitted('deleteSelected'))
            {
                $deleteFileNames = isset($input['attachedFiles']) && is_array($input['attachedFiles'])
                ? $input['attachedFiles'] : array();
                foreach ($deleteFileNames as $f) $this->cm->deleteFile($pagePath, $f);
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
                    if ($this->cm->hasPage($pagePath . $fileName))
                    {
                        $this->files['errors'][$i] = 'already exists';
                        $fileErrorOccurred = true;
                        continue;
                    }
                    $fileSize = $f['size'][$i];
                    $mimeType = $f['type'][$i];
                    $contents = file_get_contents($f['tmp_name'][$i]);
                    $this->cm->addFile($pagePath, $fileName, $fileSize, $mimeType, $contents);
                    $fileChangesMade = true;
                }
                if ($fileErrorOccurred)
                {
                    ss($this->id, $this);
                    return;
                }
            }
            // deleteSelected and upload
            if ($v->submitted('deleteSelected') || $v->submitted('upload'))
            {
                ss($this->id, $this);
                return;
            }
            // preview
            if ($v->submitted('preview'))
            {
                if (!$v->error) $this->preview = true;
                ss($this->id, $this);
                return;
            }
            // save
            if ($v->submitted('save'))
            {
                if ($v->error || $fileChangesMade)
                {
                    ss($this->id, $this);
                    return;
                }
                // Don't do the edit if the body didn't change; otherwise a
                // revision would be recorded for only a name change.
                $oldBody = trim($GLOBALS['pizza']['page']['body']);
                $newBody = trim($this->body['value']);
                $date = $this->articleDate['value'];
                if (($oldBody != $newBody) || ($date !== ''))
                    $this->cm->editPage($pagePath, $this->body['value'], false, $this->articleDate['value']);
                if (!$this->cm->renamePage($pagePath, $this->title['value']))
                {
                    ss($this->id, $this);
                    return;
                }
                // If moving from a temp page to a real page, change permissions.
                if ($this->cm->isTempPage($pagePath))
                    $this->cm->changePermissions($newPagePath, 'yyrwr-r-');
                $GLOBALS['pizza']['settings']->set($newPagePath, 'articleAuthor', $this->articleAuthor['value']);
                $GLOBALS['pizza']['settings']->set($newPagePath, 'articleSource', $this->articleSource['value']);
                $GLOBALS['pizza']['settings']->set($newPagePath, 'articleUrl', $this->articleUrl['value']);
                sc($this->id);
                // Also clear the parent's state (i.e. tempPagePath)
                sc('Articles_' . $parentPagePath);
                relocateNow($urlRoot . $newPagePath);
            }
        }

        sleep(3);
    }
}
?>
