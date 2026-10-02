<?php
// Copyright 2023 Fleming Computer.
// All Rights Reserved.
?>
<?php
class EditPage
{
    // Form inputs.
    private $attachedFiles;
    private $files;
    private $body;
    private $ogTitle;
    private $ogDescription;
    private $ogImage;

    private $id;
    private $preview;

    function __construct()
    {
        $this->id = 'EditPage_' . $GLOBALS['pizza']['pagePath'];
        $this->initThis();
        ss($this->id, $this);
    }

    function getHtml()
    {
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

        return $this->showForm();
    }

    private function initThis()
    {
        $o = sge($this->id, false);
        $this->attachedFiles = $o ? $o->attachedFiles : array('value' => '', 'error' => '');
        $this->files = $o ? $o->files : array('value' => '', 'errors' => array());
        $this->body = $o ? $o->body : array('value' => $GLOBALS['pizza']['page']['body'], 'error' => '');
        $settings = $GLOBALS['pizza']['page']['settings'];
        $this->ogTitle = $o ? $o->ogTitle : array(
            'value' => ($settings['og-title'] ?? ''),
            'error' => ''
        );
        $this->ogDescription = $o ? $o->ogDescription : array(
            'value' => ($settings['og-description'] ?? ''),
            'error' => ''
        );
        $this->ogImage = $o ? $o->ogImage : array(
            'value' => ($settings['og-image'] ?? ''),
            'error' => ''
        );
        $this->preview = $o ? $o->preview : false;
    }

    private function showForm()
    {
        $cm = $GLOBALS['pizza']['cm'];
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $toppings = $GLOBALS['pizza']['toppings'];
        ob_start();
        ?>
        <div class="page-editor">
            <form action="<?=$urlRoot?><?=$pagePath?>" enctype="multipart/form-data" method="post" name="<?=myHtmlEntities($this->id)?>">
                <input name="formId" type="hidden" value="<?=myHtmlEntities($this->id)?>">
                <?php
                if ($this->preview)
                {
                    $body = $toppings->applyToppings($this->body['value']);
                    $body = $toppings->deactivateForms($body);
                    ?>
                    <div class="preview">
                        <div><?=$body?></div>

                        <hr style="clear: both;">
                    </div>
                    <?php
                    $this->preview = false;
                }
                ?>

                <div id="editor-tabs">
                    <ul>
                        <li><a href="#editor-tab-body">Editor</a></li>
                        <li><a href="#editor-tab-sharing">Sharing</a></li>
                        <li><a href="#editor-tab-files">Files</a></li>
                    </ul>

                    <div id="editor-tab-body">
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
                            <input class="submit" name="save" type="submit" value="Save">
                            <input class="submit" name="preview" type="submit" value="Preview">
                            <input class="submit" name="done" type="submit" value="Done">
                        </div>
                    </div>

                    <div id="editor-tab-sharing" style="margin-top: 0.5em;">
                        <?php
                        $thisPage = urlencode($urlRoot . $pagePath);
                        ?>
                        <p>Control how your page looks when shared on social media. Use <a href="https://opengraph.xyz/url/<?=$thisPage?>" target="_blank">Open Graph Preview</a> to see how your shared link looks, and use <a href="https://developers.facebook.com/tools/debug/?q=<?=$thisPage?>" target="_blank">Facebook Sharing Debugger</a> to refresh Facebook's cache.</p>

                        <div>
                            <?php
                            $class = $this->ogTitle['error'] != '' ? 'class="error-placeholder"' : '';
                            ?>
                            <input <?=$class?> name="ogTitle" placeholder="Title" type="text" value="<?=myHtmlEntities($this->ogTitle['value'])?>" />
                            <?php
                            if ($this->ogTitle['error'] != '')
                            {
                                ?>
                                <span class="error">←<?=myHtmlEntities($this->ogTitle['error'])?></span>
                                <?php
                            }
                            ?>
                        </div>

                        <div>
                            <?php
                            $class = $this->ogDescription['error'] != '' ? 'class="error-placeholder"' : '';
                            ?>

                            <textarea name="ogDescription" placeholder="Description"><?=myHtmlEntities($this->ogDescription['value'])?></textarea>
                            <?php
                            if ($this->ogDescription['error'] != '')
                            {
                                ?>
                                <div class="error" style="clear: both;">↑<?=myHtmlEntities($this->ogDescription['error'])?>↑</div>
                                <?php
                            }
                            ?>
                        </div>

                        <div>
                            <?php
                            $class = $this->ogImage['error'] != '' ? 'class="error-placeholder"' : '';
                            ?>
                            <input <?=$class?> name="ogImage" placeholder="Image URL" type="text" value="<?=myHtmlEntities($this->ogImage['value'])?>" />
                            <?php
                            if ($this->ogImage['error'] != '')
                            {
                                ?>
                                <span class="error">←<?=myHtmlEntities($this->ogImage['error'])?></span>
                                <?php
                            }
                            ?>
                        </div>

                        <div>
                            <input class="submit" name="saveSharing" type="submit" value="Save">
                            <input class="submit" name="done" type="submit" value="Done">
                        </div>
                    </div>

                    <div id="editor-tab-files" style="margin-top: 0.5em;">
                    <div data-filer="hide">
                        <?php
                        $fileInfo = $cm->getFileInfo($pagePath);
                        if ($fileInfo !== false)
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
                                    // $imgUrl = $urlRoot . $pagePath . $mFileName . '?width=72&amp;height=24';
                                    $imgUrl = $urlRoot . $pagePath . $mFileName . '?width=48&amp;height=16';
                                }
                                else if (substr($f['mimeType'], -3, 3) == 'pdf')
                                    $imgUrl = "$urlRoot/images/mime/pdf.gif";
                                else if (substr($f['mimeType'], 0, 5) == 'audio')
                                    $imgUrl = "$urlRoot/images/mime/audio.gif";
                                ?>
                                <input name="attachedFiles[]" type="checkbox" value="<?=$mFileName?>">
                                <div style="display: inline-block; text-align: center; width: 48px;">
                                    <img src="<?=$imgUrl?>" border="0" alt="<?=$mFileName?>" style="height: 16px; max-width: 48px;">
                                </div>
                                <a href="<?=$urlRoot?><?=$pagePath?><?=$mFileName?>" target="_blank"><?=$mFileName?></a> (<?=$fileDetails?>)<br>
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
                    <input class="submit" id="filer-done" name="done" type="submit" value="Done">
                    </div>
                </div>
            </form>
        </div>
        <?php
        addJavaScript("$urlRoot/js/editor.js");
        ?>
        <script type="text/javascript">
            $(document).ready(function() {
                $("#editor-tabs").pizzaEditor();
            });
        </script>
        <?php
        return ob_get_clean();
    }

    private function validateInput($input)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $cm = $GLOBALS['pizza']['cm'];
        $v = $GLOBALS['pizza']['v'];
        $v->setMethod('post');
        $v->reset();

        if ($v->submitted('done'))
        {
            unlockPage();
            sc($this->id);
            relocateNow($urlRoot . currentPath());
        }

        if (
            $v->submitted('deleteSelected')
            || $v->submitted('upload')
            || $v->submitted('preview')
            || $v->submitted('save')
            || $v->submitted('saveSharing')
        ) {
            $this->body = $v->checkLength('body', 2, 100000);
            $this->ogTitle = $v->checkLength('ogTitle', 0, 60);
            $this->ogDescription = $v->checkLength('ogDescription', 0, 200);
            $this->ogImage = $v->checkLength('ogImage', 0, 255);
            // Replace space with plus.
            $this->ogImage['value'] = str_replace(' ', '+', $this->ogImage['value']);
            if ($v->error)
            {
                ss($this->id, $this);
                return;
            }
            $pagePath = currentPath();
            if ($cm->isReservedPage($pagePath)) relocateNow($urlRoot . currentUri());
            $fileChangesMade = false;
            // Delete files whose checkboxes were set.
            if ($v->submitted('deleteSelected'))
            {
                $deleteFileNames = isset($input['attachedFiles']) && is_array($input['attachedFiles'])
                ? $input['attachedFiles'] : array();
                foreach ($deleteFileNames as $f) $cm->deleteFile($pagePath, $f);
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
                    if ($cm->hasPage($pagePath . $fileName))
                    {
                        $this->files['errors'][$i] = 'already exists';
                        $fileErrorOccurred = true;
                        continue;
                    }
                    $fileSize = $f['size'][$i];
                    $mimeType = $f['type'][$i];
                    $contents = file_get_contents($f['tmp_name'][$i]);
                    $cm->addFile($pagePath, $fileName, $fileSize, $mimeType, $contents);
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
            if ($v->submitted('save') || $v->submitted('saveSharing'))
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
                if ($oldBody != $newBody)
                    $cm->editPage($pagePath, $this->body['value']);
                // Update the sharing settings.
                $s = $GLOBALS['pizza']['settings'];
                $s->set($pagePath, 'og-title', $this->ogTitle['value']);
                $s->set($pagePath, 'og-description', $this->ogDescription['value']);
                $s->set($pagePath, 'og-image', $this->ogImage['value']);

                if ($v->submitted('saveSharing'))
                {
                    ss($this->id, $this);
                    relocateNow($urlRoot . $pagePath);
                }
                else
                {
                    unlockPage();
                    sc($this->id);
                    relocateNow($urlRoot . currentPath());
                }
            }
        }

        sleep(3);
    }
}
?>
