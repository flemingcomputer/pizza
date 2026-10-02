<?php
// Copyright 2016 Fleming Computer.
// All Rights Reserved.
?>
<?php
class Toppings
{
    private $htmlElements;
    private $i;
    private $toppings;

    function __construct()
    {
        $this->htmlElements = array();
        $this->i = 0;
        $this->toppings = array();
    }

    function applyCrust($html)
    {
        $html = str_replace("\r", '', $html);
        // The element [head]...[/head] is reserved, and is used to
        // declare page-specific head entries e.g. Open Graph data. Strip out any
        // such element and store in global pizza info for applyTheme to use.
        $html = $this->cutHeadElements($html);
        // The element [html]...[/html] is reserved, and is used to denote
        // blocks of text for which no processing should take place.  Prior
        // to each transformation, we will cut out and save the
        // [html]...[/html] blocks, then paste the blocks back after all
        // transformations are performed.
        $html = $this->cutHtmlElements($html);
        // Perform basic transformations.
        $html = $this->convertOrderedLists($html);
        $html = $this->cutHtmlElements($html);
        $html = $this->convertUnorderedLists($html);
        $html = $this->cutHtmlElements($html);
        $html = $this->createParagraphs($html);
        // Permit lazy use of the characters '&', '<', and '>'.
        $html = preg_replace(
            array(
                '/&(?![A-Za-z]+;|#[0-9]+;)/',
                '/(\s+)<(\s+)/',
                '/(\s+)>(\s+)/'
            ),
            array('&amp;', '$1&lt;$2', '$1&gt;$2'),
            $html
        );
        $html = $this->pasteHtmlElements($html);
        $this->htmlElements = array();
        $this->i = 0;
        return $html;
    }

    function applyToppings($html, $skip = '')
    {
        // The 'toppings' directory can contain files of the form myclass.php
        // having a definition for the class myclass.  The 'toppings' directory
        // can also contain a directory of the form mydir which must have a
        // file within named mydir.php defining class mydir.
        if (empty($this->toppings))
        {
            foreach (glob($GLOBALS['pizza']['docRoot'] . '/toppings*/*') as $t)
            {
                $className = '';
                if (substr(basename($t), -4) == '.php')
                    $className = substr(basename($t), 0, -4);
                else
                    $className = basename($t);
                if ($className == '') continue;
                if (is_dir($t))
                {
                    if (!is_file("$t/$className.php")) continue;
                    $t = "$t/$className.php";
                }
                require_once($t);
                $this->toppings[$className] = new $className();
            }
        }
        // First, remove any explicit occurences of '[HTML#]'.
        $html = preg_replace('/\[HTML\d+\]/', '', $html);
        // Second, apply all external toppings.
        foreach ($this->toppings as $className => $topping)
        {
            if ($className == $skip) continue;
            // echo "---- html before $className ------------------\n";
            // echo "$html\n";
            // echo "------------------------------------\n";
            $html = $topping->applyTopping($html);
            // echo "---- html after $className --------------------\n";
            // echo "$html\n";
            // echo "---- htmlElements ------------------\n";
            // foreach ($this->htmlElements as $i => $h)
            //     echo "$i:  $h\n";
            // echo "------------------------------------\n";
        }
        // Third, apply all "easy-text" transformations.
        $html = $this->applyCrust($html);
        // echo "----- final ----------------------------\n";
        // echo $html;
        // exit();
        return $html;
    }

    function deactivateForms($html)
    {
        $html = preg_replace(
            array(
                '/<form([^>]*)>/',
                '/<\/form>/',
                '/<button\s+([^>]*)>/',
                '/<fieldset\s+([^>]*)>/',
                '/<input(.*)\s+\/>/',
                '/<input(.*)([^\s])\/>/',
                '/<input(.*)([^\/])>/',
                '/<option\s+([^>]*)>/',
                '/<optgroup\s+([^>]*)>/',
                '/<select\s+([^>]*)>/',
                '/<textarea\s+([^>]*)>/',
                '/\s+disabled="disabled"/'
            ),
            array(
                '<!--form$1-->',
                '<!--/form-->',
                '<button $1 disabled="disabled">',
                '<fieldset $1 disabled="disabled">',
                '<input$1 disabled="disabled" />',
                '<input$1$2 disabled="disabled"/>',
                '<input$1$2 disabled="disabled">',
                '<option $1 disabled="disabled">',
                '<optgroup $1 disabled="disabled">',
                '<select $1 disabled="disabled">',
                '<textarea $1 disabled="disabled">',
                ' disabled="disabled"'
            ),
            $html
        );
        return $html;
    }

    function error($text)
    {
        return 'Syntax error: [' . $text . "]\n";
    }

    function getString($text, $key, $default = false)
    {
        preg_match("/$key\s*=\s*([^,]+)/", $text, $matches);
        if (count($matches) == 2) return trim($matches[1]);
        return $default;
    }

    function getYesNo($text, $key, $default = false)
    {
        preg_match("/$key\s*=\s*([^,]+)/", $text, $matches);
        if (count($matches) != 2) return $default;
        $value = trim($matches[1]);
        if (($value == 'yes') || ($value == 'no')) return $value;
        return $default;
    }

    function protect($html)
    {
        $this->htmlElements[$this->i] = $html;
        $s = '[HTML' . $this->i . ']';
        $this->i++;
        return $s;
    }

    private function convertOrderedLists($html)
    {
        // Note that each qualifying line is at first enclosed in a
        // <ol>\n...\n</ol> pair.  Then, contiguous "</ol>\n<ol>" lines are
        // converted into empty strings.  Next, qualifying lines are enclosed
        // in <li>...</li> pairs.  Lastly, the whole thing is enclosed in a
        // pair of [html]...[/html] tags.
        $html = preg_replace(
            array(
                '/^(\d+\.\s.*)$/m',
                '/\n<\/ol>\n<ol>/',
                '/^\d+\.\s(.*)/m',
                '/(<ol>.*<\/ol>)/sU',
            ),
            array(
                "<ol>\n$0\n</ol>",
                '',
                '<li>$1</li>',
                '[html]$1[/html]',
            ),
            $html
        );
        return $html;
    }

    private function convertUnorderedLists($html)
    {
        // The same strategy for ordered lists above is used herein.
        $html = preg_replace(
            array(
                '/^(\*\s.*)$/m',
                '/\n<\/ul>\n<ul>/',
                '/^\*\s+(.*)/m',
                '/(<ul>.*<\/ul>)/sU',
            ),
            array(
                "<ul>\n$0\n</ul>",
                '',
                '<li>$1</li>',
                '[html]$1[/html]',
            ),
            $html
        );
        return $html;
    }

    private function createParagraphs($html)
    {
        // Apply paragraphs to lines beginning with text or inline-level
        // elements, but not to lines beginning with block-level elements.
        // 1. The first pattern converts '*-->' to '<--*-->' so it gets handled
        //    like a block element.
        // 2. The next bunch is about altering the inline elements so that they
        //    pass the test of the subsequent pattern, which selects lines not
        //    beginning with '<', "\n", or '['.
        // 3. Each qualifying line is first converted to a one-line paragraph.
        //    Then, contiguous "</p>\n<p>" lines are converted into line break
        //    plus line feed pairs.
        // 4. The next pattern restores the inline elements to original.
        // 5. The last pattern restores comment end tags.
        $html = preg_replace(
            array(
                '/^(.*)-->/m',
                '/(<a(\s+.*)?>)/U',
                '/(<b(\s+.*)?>)/U',
                '/(<br(\s+.*)?>)/U',
                '/(<cite(\s+.*)?>)/U',
                '/(<em(\s+.*)?>)/U',
                '/(<i(\s+.*)?>)/U',
                '/(<img(\s+.*)?>)/U',
                '/(<span(\s+.*)?>)/U',
                '/(<strong(\s+.*)?>)/U',
                '/(<sub(\s+.*)?>)/U',
                '/(<sup(\s+.*)?>)/U',
                '/(<u(\s+.*)?>)/U',
                // end of inline element patterns
                '/^([^<\n^[].*)$/m',
                '/<\/p>\n<p>/',
                '/__(<.*>)__/U', // restore inline elements
                '/<--(.*)-->/'
            ),
            array(
                '<--$1-->',
                '__$0__', '__$0__', '__$0__', '__$0__', '__$0__', '__$0__',
                '__$0__', '__$0__', '__$0__', '__$0__', '__$0__', '__$0__',
                // end of inline replacements
                '<p>$0</p>',
                "<br>\n",
                '$1',  // restore inline elements
                '$1-->'
            ),
            $html
        );
        return $html;
    }

    private function cutHeadElements($html)
    {
        if (preg_match('/\[head\](.*)\[\/head\]/sU', $html, $matches))
        {
            $GLOBALS['pizza']['page']['head'] = trim($matches[1]);
            $html = preg_replace('/\[head\](.*)\[\/head\]\s*/s', '', $html);
        }
        return $html;
    }

    private function cutHtmlElements($html)
    {
        // Save the [html]...[/html] elements and substitute with "[HTML#]"
        // placeholders.
        $html = preg_replace_callback(
            '/\[html\](.*)\[\/html\]/sU',
            array($this, '_cutHtmlElements'),
            $html
        );
        return $html;
    }

    private function _cutHtmlElements($matches)
    {
        $this->htmlElements[$this->i] = trim($matches[1]);
        $s = '[HTML' . $this->i . ']';
        $this->i++;
        return $s;
    }

    private function pasteHtmlElements($html)
    {
        // Replace the "[HTML#]" placeholders with their respective
        // [html]...[/html] pieces.
        $html = preg_replace_callback(
            '/\[HTML(\d+)\]/',
            function ($matches)
            {
                return $this->htmlElements[$matches[1]];
            },
            $html
        );
        return $html;
    }
}
?>
