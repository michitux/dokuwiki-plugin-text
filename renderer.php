<?php

use dokuwiki\File\PageResolver;

/**
 * Renderer for text output
 *
 * @author Michael Hamann <michael@content-space.de>
 * @author Todd Augsburger <todd@rollerorgans.com>
 */
class renderer_plugin_text extends Doku_Renderer_xhtml
{

    protected $nSpan = 0;
    protected $separator = '';

    /** @inheritdoc */
    function getFormat()
    {
        return 'text';
    }

    /** @inheritdoc */
    public function startSectionEdit($start, $type, $title = null)
    {
    }

    /** @inheritdoc */
    public function finishSectionEdit($end = null, $hid = null)
    {
    }

    /**
     * @inheritdoc
     * Use specific text support if available, otherwise use xhtml renderer and strip tags
     */
    public function plugin($name, $data, $state = '', $match = '')
    {
        /** @var DokuWiki_Syntax_Plugin $plugin */
        $plugin = plugin_load('syntax', $name);
        if ($plugin === null) return;

        if (!$plugin->render($this->getFormat(), $this, $data)) {

            // probably doesn't support text, so use stripped-down xhtml
            $tmpData = $this->doc;
            $this->doc = '';
            if ($plugin->render('xhtml', $this, $data) && ($this->doc != '')) {
                $search = array('@<script[^>]*?>.*?</script>@si', // javascript
                    '@<style[^>]*?>.*?</style>@si',                // style tags
                    '@<[\/\!]*?[^<>]*?>@si',                        // HTML tags
                    '@<![\s\S]*?--[ \t\n\r]*>@',                    // multi-line comments
                    '@\s+@',                                         // extra whitespace
                );
                $this->doc = $tmpData . DOKU_LF .
                    trim(html_entity_decode(preg_replace($search, ' ', $this->doc), ENT_QUOTES)) .
                    DOKU_LF;
            } else {
                $this->doc = $tmpData;
            }
        }

    }

    /** @inheritdoc */
    public function document_start()
    {
        global $ID;

        $this->doc = '';
        $this->toc = array();
        $this->footnotes = array();
        $this->store = '';
        $this->nSpan = 0;
        $this->separator = '';

        $metaheader = array();
        $metaheader['Content-Type'] = 'text/plain; charset=utf-8';
        //$metaheader['Content-Disposition'] = 'attachment; filename="noname.txt"';
        $meta = array();
        $meta['format']['text'] = $metaheader;
        p_set_metadata($ID, $meta);
    }

    /** @inheritdoc */
    public function document_end()
    {
        if (count($this->footnotes) > 0) {
            $this->doc .= DOKU_LF;

            $id = 0;
            foreach ($this->footnotes as $footnote) {
                $id++;   // the number of the current footnote

                // check its not a placeholder that indicates actual footnote text is elsewhere
                if (substr($footnote, 0, 5) != "@@FNT") {
                    $this->doc .= $id . ') ';
                    // get any other footnotes that use the same markup
                    $alt = array_keys($this->footnotes, "@@FNT$id");
                    if (count($alt)) {
                        foreach ($alt as $ref) {
                            $this->doc .= ($ref + 1) . ') ';
                        }
                    }
                    $this->doc .= $footnote . DOKU_LF;
                }
            }
        }

        // Prepare the TOC
        global $conf;
        if ($this->info['toc'] && is_array($this->toc) && $conf['tocminheads'] && count($this->toc) >= $conf['tocminheads']) {
            global $TOC;
            $TOC = $this->toc;
        }

        // make sure there are no empty paragraphs
        $this->doc = preg_replace('#' . DOKU_LF . '\s*' . DOKU_LF . '\s*' . DOKU_LF . '#', DOKU_LF . DOKU_LF, $this->doc);
    }

    /** @inheritdoc */
    public function header($text, $level, $pos, $returnonly = false)
    {
        $this->doc .= DOKU_LF . $text . DOKU_LF;
    }

    /** @inheritdoc */
    public function section_open($level)
    {
    }

    /** @inheritdoc */
    public function section_close()
    {
        $this->doc .= DOKU_LF;
    }

    /** @inheritdoc */
    public function cdata($text)
    {
        $this->doc .= $text;
    }

    /** @inheritdoc */
    public function p_open()
    {
    }

    /** @inheritdoc */
    public function p_close()
    {
        $this->doc .= DOKU_LF;
    }

    /** @inheritdoc */
    public function linebreak()
    {
        $this->doc .= DOKU_LF;
    }

    /** @inheritdoc */
    public function hr()
    {
        $this->doc .= '--------' . DOKU_LF;
    }

    /** @inheritdoc */
    public function strong_open()
    {
    }

    /** @inheritdoc */
    public function strong_close()
    {
    }

    /** @inheritdoc */
    public function emphasis_open()
    {
    }

    /** @inheritdoc */
    public function emphasis_close()
    {
    }

    /** @inheritdoc */
    public function underline_open()
    {
    }

    /** @inheritdoc */
    public function underline_close()
    {
    }

    /** @inheritdoc */
    public function monospace_open()
    {
    }

    /** @inheritdoc */
    public function monospace_close()
    {
    }

    /** @inheritdoc */
    public function subscript_open()
    {
    }

    /** @inheritdoc */
    public function subscript_close()
    {
    }

    /** @inheritdoc */
    public function superscript_open()
    {
    }

    /** @inheritdoc */
    public function superscript_close()
    {
    }

    /** @inheritdoc */
    public function deleted_open()
    {
    }

    /** @inheritdoc */
    public function deleted_close()
    {
    }

    /** @inheritdoc */
    public function footnote_open()
    {

        // move current content to store and record footnote
        $this->store = $this->doc;
        $this->doc = '';
    }

    /** @inheritdoc */
    public function footnote_close()
    {

        // recover footnote into the stack and restore old content
        $footnote = $this->doc;
        $this->doc = $this->store;
        $this->store = '';

        // check to see if this footnote has been seen before
        $i = array_search($footnote, $this->footnotes);

        if ($i === false) {
            // its a new footnote, add it to the $footnotes array
            $id = count($this->footnotes) + 1;
            $this->footnotes[] = $footnote;
        } else {
            // seen this one before, translate the index to an id and save a placeholder
            $i++;
            $id = count($this->footnotes) + 1;
            $this->footnotes[] = "@@FNT" . ($i);
        }

        // output the footnote reference and link
        $this->doc .= ' ' . $id . ')';
    }

    /** @inheritdoc */
    public function listu_open($classes = null)
    {
    }

    /** @inheritdoc */
    public function listu_close()
    {
        $this->doc .= DOKU_LF;
    }

    /** @inheritdoc */
    public function listo_open($classes = null)
    {
    }

    /** @inheritdoc */
    public function listo_close()
    {
        $this->doc .= DOKU_LF;
    }

    /** @inheritdoc */
    public function listitem_open($level, $node = false)
    {
    }

    /** @inheritdoc */
    public function listitem_close()
    {
    }

    /** @inheritdoc */
    public function listcontent_open()
    {
    }

    /** @inheritdoc */
    public function listcontent_close()
    {
        $this->doc .= DOKU_LF;
    }

    /** @inheritdoc */
    public function unformatted($text)
    {
        $this->doc .= $text;
    }

    /** @inheritdoc */
    public function quote_open()
    {
    }

    /** @inheritdoc */
    public function quote_close()
    {
        $this->doc .= DOKU_LF;
    }

    /** @inheritdoc */
    public function preformatted($text)
    {
        $this->doc .= $text . DOKU_LF;
    }

    /** @inheritdoc */
    public function file($text, $language = null, $filename = null, $options = null)
    {
        $this->doc .= $text . DOKU_LF;
    }

    /** @inheritdoc */
    public function code($text, $language = null, $filename = null, $options = null)
    {
        $this->preformatted($text);
    }

    /** @inheritdoc */
    public function acronym($acronym)
    {
        if (array_key_exists($acronym, $this->acronyms)) {
            $title = $this->acronyms[$acronym];
            $this->doc .= $acronym . ' (' . $title . ')';
        } else {
            $this->doc .= $acronym;
        }
    }

    /** @inheritdoc */
    public function smiley($smiley)
    {
        $this->doc .= $smiley;
    }

    /** @inheritdoc */
    public function entity($entity)
    {
        if (array_key_exists($entity, $this->entities)) {
            $this->doc .= $this->entities[$entity];
        } else {
            $this->doc .= $entity;
        }
    }

    /** @inheritdoc */
    public function multiplyentity($x, $y)
    {
        $this->doc .= $x . 'x' . $y;
    }

    /** @inheritdoc */
    public function singlequoteopening()
    {
        global $lang;
        $this->doc .= $lang['singlequoteopening'];
    }

    /** @inheritdoc */
    public function singlequoteclosing()
    {
        global $lang;
        $this->doc .= $lang['singlequoteclosing'];
    }

    /** @inheritdoc */
    public function apostrophe()
    {
        global $lang;
        $this->doc .= $lang['apostrophe'];
    }

    /** @inheritdoc */
    public function doublequoteopening()
    {
        global $lang;
        $this->doc .= $lang['doublequoteopening'];
    }

    /** @inheritdoc */
    public function doublequoteclosing()
    {
        global $lang;
        $this->doc .= $lang['doublequoteclosing'];
    }

    /** @inheritdoc */
    public function camelcaselink($link, $returnonly = false)
    {
        $this->internallink($link, $link);
    }

    /** @inheritdoc */
    public function locallink($hash, $name = null, $returnonly = false)
    {
        $name = $this->_getLinkTitle($name, $hash, $isImage);
        $this->doc .= $name;;
    }

    /** @inheritdoc */
    public function internallink($id, $name = null, $search = null, $returnonly = false, $linktype = 'content')
    {
        global $ID;
        // default name is based on $id as given
        $default = $this->_simpleTitle($id);
        $resolver = new PageResolver($ID);
        $id = $resolver->resolveId($id);

        $name = $this->_getLinkTitle($name, $default, $isImage, $id, $linktype);
        if ($returnonly) {
            return $name;
        } else {
            $this->doc .= $name;
        }
    }

    /** @inheritdoc */
    public function externallink($url, $name = null, $returnonly = false)
    {
        $this->doc .= $this->_getLinkTitle($name, $url, $isImage);
    }

    /** @inheritdoc */
    public function interwikilink($match, $name, $wikiName, $wikiUri, $returnonly = false)
    {
        $this->doc .= $this->_getLinkTitle($name, $wikiUri, $isImage);
    }

    /** @inheritdoc */
    public function windowssharelink($url, $name = null, $returnonly = false)
    {
        $this->doc .= $this->_getLinkTitle($name, $url, $isImage);
    }

    /** @inheritdoc */
    public function emaillink($address, $name = null, $returnonly = false)
    {
        $name = $this->_getLinkTitle($name, '', $isImage);
        $address = html_entity_decode(obfuscate($address), ENT_QUOTES, 'UTF-8');
        if (empty($name)) {
            $name = $address;
        }
        $this->doc .= $name;
    }

    /** @inheritdoc */
    public function internalmedia($src, $title = null, $align = null, $width = null,
                                  $height = null, $cache = null, $linking = null, $return = false)
    {
        $this->doc .= $title;
    }

    /** @inheritdoc */
    public function externalmedia($src, $title = null, $align = null, $width = null,
                                  $height = null, $cache = null, $linking = null, $return = false)
    {
        $this->doc .= $title;
    }

    /** @inheritdoc */
    public function rss($url, $params)
    {
    }

    /** @inheritdoc */
    public function table_open($maxcols = null, $numrows = null, $pos = null, $classes = null)
    {
    }

    /** @inheritdoc */
    public function table_close($pos = null)
    {
        $this->doc .= DOKU_LF;
    }

    /** @inheritdoc */
    public function tablethead_open()
    {
    }

    /** @inheritdoc */
    public function tablethead_close()
    {
    }

    /** @inheritdoc */
    public function tabletfoot_open()
    {
    }

    /** @inheritdoc */
    public function tabletfoot_close()
    {
    }

    /** @inheritdoc */
    public function tabletbody_open()
    {
    }

    /** @inheritdoc */
    public function tabletbody_close()
    {
    }

    /** @inheritdoc */
    public function tablerow_open($classes = null)
    {
        $this->separator = '';
    }

    /** @inheritdoc */
    public function tablerow_close()
    {
        $this->doc .= DOKU_LF;
    }

    /** @inheritdoc */
    public function tableheader_open($colspan = 1, $align = null, $rowspan = 1, $classes = null)
    {
        $this->tablecell_open();
    }

    /** @inheritdoc */
    public function tableheader_close()
    {
        $this->tablecell_close();
    }

    /** @inheritdoc */
    public function tablecell_open($colspan = 1, $align = null, $rowspan = 1, $classes = null)
    {
        $this->nSpan = $colspan;
        $this->doc .= $this->separator;
        $this->separator = ', ';
    }

    /** @inheritdoc */
    public function tablecell_close()
    {
        if ($this->nSpan > 0) {
            $this->doc .= str_repeat(',', $this->nSpan - 1);
        }
        $this->nSpan = 0;
    }

    /** @inheritdoc */
    public function _getLinkTitle($title, $default, &$isImage, $id = null, $linktype = 'content')
    {
        $isImage = false;
        if (is_array($title)) {
            $isImage = true;
            if (!is_null($default) && ($default != $title['title']))
                return $default . " " . $title['title'];
            else
                return $title['title'];
        } elseif (is_null($title) || trim($title) == '') {
            if (useHeading($linktype) && $id) {
                $heading = p_get_first_heading($id);
                if ($heading) {
                    return $this->_xmlEntities($heading);
                }
            }
            return $this->_xmlEntities($default);
        } else {
            return $this->_xmlEntities($title);
        }
    }

    /** @inheritdoc */
    public function _xmlEntities($string)
    {
        return $string; // nothing to do for text
    }

    /** @inheritdoc */
    public function _formatLink($link)
    {
        if (!empty($link['name'])) {
            return $link['name'];
        } elseif (!empty($link['title'])) {
            return $link['title'];
        }
        return $link['url'];
    }
}
