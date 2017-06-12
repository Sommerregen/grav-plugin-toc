<?php
/**
 * Toc
 *
 * This file is part of Grav Toc plugin.
 *
 * Dual licensed under the MIT or GPL Version 3 licenses, see LICENSE.
 * http://benjamin-regler.de/license/
 */

namespace Grav\Plugin;

use Grav\Common\Grav;
use ForceUTF8\Encoding;
use RocketTheme\Toolbox\Event\Event;

/**
 * Toc
 *
 * Helper class to automagically generate a (minified) Table of Contents
 * based on special markers in the document and adds it into the
 * resulting HTML document.
 */
class Toc
{
    /**
     * Regex for Markdown (setext-style and atx-style headers):
     *   ~^(?P<hashes>\#{1,6})?[ ]*
     *       (?P<heading>.+?)(?(1)\#*|[ ]*\n(=+|-+)[ ]*)\n+~m';
     *
     * @var string
     */
    protected $regex = [
        'html' => '~<(?P<tag>pre|code|blockquote|q|cite|h\d+)\s*(?P<attr>[^>]*)>(?P<text>.*?)</\1>~ims',
        'markdown' => "~^(?P<tag>\#{1,6})?[ ]*(?P<text>.+?)(?(1)\#*|[ ]*\n(=+|-+)[ ]*)\n+~m"
    ];

    /**
     * Create and link the table of contents at the top of the file.
     *
     * @param  string $content The content to be use for creating the TOC
     *
     * @return array           Returns an array of headings in the format:
     *                          $offset => [
     *                            'tag' => ..., 'level' => ...,
     *                            'text' => ..., 'id' => ...
     *                          ]
     */
    public function createToc($content, $options = [], $origin = 'html')
    {
        $toc = [];
        $counter = [];

        if (preg_match_all($this->regex[$origin], $content, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            $language = $options->get('language');

            foreach ($matches as $match) {
                $offset = $match[0][1];

                $tag = strtolower($match['tag'][0]);
                $text = trim($match['text'][0]);

                if ($origin == 'markdown') {
                    $tag = 'h' . strlen($tag);
                }

                // Ignore headings in code, pre or blockquote environments
                if (!$text || $tag{0} !== 'h') {
                    continue;
                }

                // Extract information from HTML tag
                $level = (int) mb_substr($tag, 1);

                // Expand tag attributes
                $attributes = $this->parseAttributes($match['attr'][0]);
                $id = isset($attributes['id']) ? $attributes['id'] : $this->hyphenize($text, $options, $language);

                // Replace empty id with hash of text
                if (strlen($id) == 0) {
                    $id = substr(md5($text), 0, 6);
                }

                if (isset($counter[$id])) {
                    $id = $id . '-' . $counter[$id]++;
                } else {
                    $counter[$id] = 1;
                }

                // Prevent TOC and MINITOC insertion in headings
                $text = str_ireplace(['[TOC]', '[MINITOC]'],
                    ['&#91;TOC&#93;', '&#91;MINITOC&#93;'], $text);

                $toc[$offset] = [
                    'tag' => $tag,
                    'level' => $level,
                    'indent' => $level - 1,
                    'text' => $text,
                    'id' => $id,
                ];
            }
        }

        // Create tree of headings and their levels
        return $this->mapTree($toc);
    }

    /**
     * Tocify content, i.e. insert anchor- and permalinks into headings.
     *
     * @param  string $content The content to be tocified
     * @param  array  $options Array of options for the TOC filter
     *
     * @return string          The content with inserted anchor- and
     *                         permalinks in headings
     */
    public function tocify($content, $options = [])
    {
        // Change regex, i.e. allow headers in (block-)quotes being parsed
        $regex = str_replace('blockquote|q|cite|', '', $this->regex['html']);

        $counter = [];
        $content = preg_replace_callback($regex,
            function($match) use ($options, &$counter) {
                $tag = strtolower($match['tag']);
                $text = trim($match['text']);

                // Don't consider headings in code or pre environments
                if (($tag{0} !== 'h') || (mb_strlen($text) == 0)) {
                    // Ignore empty headers, too
                    return $match[0];
                }

                // Extract informations from HTML tags
                $level = $indent = (int) mb_substr($tag, 1);

                // Expand tag attributes
                $language = $options->get('language');
                $attributes = $this->parseAttributes($match['attr']);
                $id = isset($attributes['id']) ? $attributes['id'] : $this->hyphenize($text, $options, $language);

                $classes = isset($attributes['class']) ? $attributes['class'] . ' headeranchor' : 'headeranchor';

                // Replace empty id with hash of text
                if (strlen($id) == 0) {
                    $id = substr(md5($text), 0, 6);
                }

                // Increment counter on same heading names
                if (isset($counter[$id])) {
                    $id = $id . '-' . $counter[$id]++;
                } else {
                    $counter[$id] = 1;
                }

                // Add permalink
                if ($options->get('permalink')) {
                    $placement = $options->get('placement', 'left');
                    $visible = $options->get('visible', 'hover');

                    // Compile custom configurations for header link
                    $extra = ' headeranchor-link--' . $placement;
                    $extra .= ' headeranchor-visible--' . $visible;

                    // Compile default classes
                    $default_classes = $options->get('class', []);
                    if (is_array($default_classes)) {
                        $default_classes = implode(' ', $default_classes);
                    }

                    $extra .= ' ' . ltrim(implode(' ', [$options->get('hover', ''), $default_classes]));

                    // Load header anchor link icon
                    $icon = $options->get('icon', '#');

                    $text = sprintf('<a class="headeranchor-link%4$s" aria-hidden="true" href="#%1$s" title="Permanent link: %2$s" data-icon="%5$s">%3$s</a>', $id, strip_tags($text), $text, rtrim($extra), $icon);
                }

                // Add id attribute (and a "headeranchor" class) if permalinks or anchorlinks are used
                $link = $options->get('anchorlink', $options->get('permalink'));
                $attributes += $link ? ['id' => $id] : [];
                if ($link) {
                  $attributes['class'] = $classes;
                }

                // Prevent TOC and MINITOC insertion in headings
                $text = str_ireplace(['[TOC]', '[MINITOC]'], ['&#91;TOC&#93;', '&#91;MINITOC&#93;'], $text);

                // Stringify HTML attributes
                $attributes = $this->htmlAttributes($attributes);

                // Return tag with its text content
                return "<$tag$attributes>$text</$tag>";
          }, $content);

        return $content;
    }

    /**
     * Process contents i.e. apply TOC filer to the content.
     *
     * @param  string     $content The content to render
     * @param  array      $options Options to be passed to the renderer
     * @param  null|Page  $page    Null or a page instance
     *
     * @return string              The rendered contents.
     */
    public function render($content, $options = [], $page = null)
    {
        /** @var Twig $twig */
        $twig = Grav::instance()['twig'];

        // Save current user language
        $options->set('language', $page->language());

        // Generate Toc
        $replacements = $this->generateToc($content, $options);
        $regex = '~(<p>)?\s*\[(?P<type>(?:MINI)?TOC)\]\s*(?(1)</p>)~i';

        if (!$replacements) {
            // Hide (mini-)toc marker
            return preg_replace($regex, '', $content);
        }

        // Tocify content
        $content = $this->tocify($content, $options);

        // Replace TOC and MINITOC placeholders
        $content = preg_replace_callback($regex,
            function($match) use ($replacements, $twig, $options) {
                static $i = 0;

                $vars['toc'] = $replacements[$i++] + $options->toArray();
                $template = 'plugins/toc/toc' . TEMPLATE_EXT;
                return $twig->processTemplate($template, $vars);
        }, $content);

        // Return modified content
        return $content;
    }

    /**
     * Generate a TOC of a given document.
     *
     * @param  string $content The content the TOC has to be generated for
     * @param  array  $options A list of options
     *
     * @return array           An array of TOCs
     */
    public function generateToc($content, $options = [])
    {
        $replacements = [];
        // Find all occurrences of TOC and MINITOC in content
        $regex = '~(<p>)?\s*\[(?P<type>(?:MINI)?TOC)\]\s*(?(1)</p>)~i';
        if (preg_match_all($regex, $content, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === false) {
            return $content;
        }

        // Generate TOC
        $toc = $this->createToc($content, $options);
        if (!$toc) {
            // Hide (mini-)toc marker
            return [];
        }

        foreach ($matches as $match) {
            $offset = $match[0][1];
            $type = strtolower($match['type'][0]);

            // Initialize variables
            $current = -1;
            $minitoc = [];

            if ($type == 'toc') {
                $minitoc = $toc;
            } else {
                // Get current (sub-)heading
                foreach ($toc as $index => $heading) {
                    if ($index < $offset) {
                        $current = $index;
                    } else {
                        $level = ($current > -1) ? $toc[$current]['level'] : -1;
                        if ($heading['level'] > $level) {
                            $minitoc[$index] = $heading;
                        } else {
                            break;
                        }
                    }
                }
            }

            // Save rendered TOC for later replacement
            $replacements[] = [
                'list' => $minitoc,
                'type' => $type,
                'heading' => ($current > -1) ? $toc[$current] : null,
            ];
        }

        return $replacements;
    }

    /**
     * Map a list of headings to a flattened tree.
     *
     * @param  array $list A list with headings
     * @return array       A flattened tree of $list
     */
    protected function mapTree(array $list)
    {
        static $indent = -1;

        if (!$list) {
            return $list;
        }

        // Adjust TOC indentation based on baselevel
        $baselevel = min(array_map(function($elem) {
            return $elem['level'];
        }, $list));

        $toc = [];
        $subtoc = [];
        $indent++;

        // Create Toc tree
        foreach ($list as $offset => $heading) {
            if ($heading['level'] == $baselevel) {
                if (count($subtoc)) {
                    $toc += $this->mapTree($subtoc);
                    $subtoc = [];
                }

                $heading['indent'] = (int) $indent;
                $toc[$offset] = $heading;
            } elseif ($heading['level'] > $baselevel) {
                $subtoc[$offset] = $heading;
            }
        }

        if (count($subtoc) > 0) {
            $toc += $this->mapTree($subtoc);
        }

        $indent--;
        return $toc;
    }

    /**
     * Parse HTML attributes from a tag.
     *
     * @param  string $text The attributes from a HTML tag as a string.
     *
     * @return array        Returns the parsed attributes as an indexed
     *                      array
     */
    protected function parseAttributes($text)
    {
        $attributes = [];
        $pattern = '~(?(DEFINE)
            (?<name>[a-zA-Z][a-zA-Z0-9-:]*)
            (?<value_double>"[^"]+")
            (?<value_single>\'[^\']+\')
            (?<value_none>[^\s>]+)
            (?<value>((?&value_double)|(?&value_single)|(?&value_none)))
          )
          (?<n>(?&name))(=(?<v>(?&value)))?~xs';

        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attributes[$match['n']] = isset($match['v']) ? trim($match['v'], '\'"') : null;
            }
        }
        return $attributes;
    }

    /**
     * Convert an array of attributes into its HTML representation.
     *
     * @param  array  $attributes The attributes to be converted to a
     *                            HTML string
     *
     * @return string             The converted attributes
     */
    protected function htmlAttributes(array $attributes = [])
    {
        foreach ($attributes as $attribute => &$data) {
            $data = implode(' ', (array) $data);
            $data = $attribute . '="' . htmlspecialchars($data, ENT_QUOTES, 'UTF-8') . '"';
        }
        return $attributes ? ' ' . implode(' ', $attributes) : '';
    }

    /**
     * Converts a word "into-it-s-hyphenated-version" (UTF-8 safe).
     *
     * A hyphenated word must begin with a letter ([A-Za-z]) and may be
     * followed by any number of letters, digits ([0-9]), hyphens ("-"),
     * underscores ("_"), colons (":"), and periods (".").
     *
     * @param  string $word     Word to hyphenate
     * @param  array  $options  A list of options
     * @param  array  $language A language code used to convert the word
     *
     * @return string           The hyphenated word
     */
    protected function hyphenize($word, $options = [], $language = null)
    {
        /** @var Grav\Common\Language\Language $l */
        $l = Grav::instance()['language'];

        // Set locale for transliterating Unicode text to plain ASCII text
        $locale = setlocale(LC_CTYPE, 0);
        setlocale(LC_CTYPE, 'en_US.UTF8');

        // Ensure word is UTF-8 encoded
        $text = html_entity_decode($word, ENT_COMPAT, 'UTF-8');

        // Strip tags
        $text = strip_tags($text);

        // Perform some language dependent replacements
        $lang = $language ?: $l->getLanguage();
        $replacements = $l->translate('PLUGINS.TOC.PATTERNS', [$lang], true);
        if (is_array($replacements)) {
            $text = preg_replace(array_keys($replacements), $replacements, $text);
        }

        // Trim and transliterate
        $text = Encoding::toLatin1(Encoding::toUTF8(trim($text, '-')));

        // Lowercase
        $text = strtolower($text);

        // Remove unwanted characters and duplicate dashes
        $text = preg_replace('~[^-\w]+~', '', $text);

        // Trim dashes from the beginning and end of string
        $text = trim($text, '.-_ ');

        // Truncate string
        if ($options && $options->get('slug.truncate')) {
            $limit = $options->get('slug.length', 32);
            $break = $options->get('slug.break', '-');
            $pad = $options->get('slug.pad', '-...');
            $level = $options->get('slug.granularity', 'words');

            $text = $this->truncate($text, $limit, $break, $pad, $level);
        }

        // Restore locale
        setlocale(LC_CTYPE, $locale);
        $text = trim($text);

        // Return hyphenated word or provide default
        return $text ?: $l->translate('PLUGINS.TOC.NOT_AVAILABLE', [$lang]);
    }

    /**
     * Truncates a string to a maximum length at word or character boundaries.
     *
     * @param  string  $string      The string which should be truncated
     * @param  integer $limit       The maximum length the string should have
     *                              after truncating
     * @param  string  $break       The break delimiter to divide the string
     *                              into pieces of words.
     * @param  string  $pad         Added to the end of the truncated string
     * @param  string  $granularity Truncate at "words" or at "characters"
     *                              level
     *
     * @return string               The truncated string
     */
    protected function truncate($string, $limit = 32, $break = '-', $pad = '-...', $granularity = 'words')
    {
        if (mb_strlen($string) > $limit) {
            // Truncate string to a maximum length
            $truncated = mb_substr($string, 0, $limit);

            // Truncate at words granularity
            if ($granularity == 'words') {
                $breakpoint = mb_strrpos($truncated, $break);
                if ($breakpoint !== false) {
                    $truncated = mb_substr($string, 0, $breakpoint);
                }
            }

            // Add truncate marker to the end of the string
            $string = preg_replace('~(\w)[^\p{L}]?$~', '$1' . $pad, $truncated);
        }

        return $string;
    }
}
