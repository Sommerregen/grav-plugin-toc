<?php
/**
 * Toc v1.4.1
 *
 * This plugin automagically generates a (minified) Table of Contents
 * based on special markers in the document and adds it into the
 * resulting HTML document.
 *
 * Dual licensed under the MIT or GPL Version 3 licenses, see LICENSE.
 * http://benjamin-regler.de/license/
 *
 * @package     Toc
 * @version     1.4.1
 * @link        <https://github.com/sommerregen/grav-plugin-external-links>
 * @author      Benjamin Regler <sommerregen@benjamin-regler.de>
 * @copyright   2015-2017, Benjamin Regler
 * @license     <http://opensource.org/licenses/MIT>        MIT
 * @license     <http://opensource.org/licenses/GPL-3.0>    GPLv3
 */

namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Data\Data;
use Grav\Common\Page\Page;
use Grav\Plugin\Shortcodes
;
use RocketTheme\Toolbox\Event\Event;

/**
 * Toc
 * @package Grav\Plugin\Toc
 */
class TocPlugin extends Plugin
{
    /**
     * Return a list of subscribed events.
     *
     * @return array    The list of events of the plugin of the form
     *                      'name' => ['method_name', priority].
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
        ];
    }

    /**
     * Initialize configuration
     */
    public function onPluginsInitialized()
    {
        if ($this->isAdmin()) {
            $this->active = false;
            return;
        }

        $this->enable([
            // Page
            'onPageContentProcessed' => ['onPageContentProcessed', 0],

            // Twig
            'onTwigInitialized' => ['onTwigInitialized', 0],
            'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],

            // Shortcodes
            'onShortcodesInitialized' => ['onShortcodesInitialized', 0]
        ]);
    }

    /**
     * Apply TOC filter to content, when each page has not been
     * cached yet.
     *
     * @param  Event  $event The event when 'onPageContentProcessed' was
     *                       fired.
     */
    public function onPageContentProcessed(Event $event)
    {
        /** @var Page $page */
        $page = $event['page'];

        $config = $this->mergeConfig($page);

        $active = $config->get('active', $config->get('process'));
        if ($active && $config->get('enabled')) {
            // Get content, apply TocFilter and save modified page content
            $content = $page->getRawContent();
            $page->setRawContent(
                $this->tocFilter($content, $config->toArray(), $page)
            );
        }
    }

    /**
     * Initialize Twig configuration and filters.
     */
    public function onTwigInitialized()
    {
        /** @var Twig_Environment $twig */
        $twig = $this->grav['twig']->twig();

        // Register and expose plugin filters
        $filters = ['toc', 'tocify'];
        foreach ($filters as $filter) {
            $method = [$this, strtolower($filter) . 'Filter'];
            $filter = new \Twig_SimpleFilter($filter, $method, ['is_safe' => ['html']]);
            $twig->addFilter($filter);
        }
    }

    /**
     * Add current directory to Twig lookup paths.
     */
    public function onTwigTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    /**
     * Set needed variables to display a table of contents.
     */
    public function onTwigSiteVariables()
    {
        if ($this->config->get('plugins.toc.built_in_css')) {
            $this->grav['assets']->add('plugin://toc/assets/css/toc.css');
        }
    }

    /**
     * Filter to automatically create a (minified) table of contents.
     *
     * @param  string $content The content to be filtered
     * @param  array  $params  Array of options for the TOC filter
     *
     * @return string          The content with inserted anchor- and
     *                         permalinks in headings and table of contents
     *                         blocks
     */
    public function tocFilter($content, $params = [])
    {
        // Resolve page and page content
        if ($content instanceof Page) {
            $page = $content;
            $content = $page->content();
        } else {
            $page = func_num_args() > 2 ? func_get_arg(2) : $this->grav['page'];
        }

        // Get custom user configuration
        $config = $this->mergeConfig($page, true, $params);

        // Render Toc
        return $this->init()->render($content, $config, $page);
    }

    /**
     * Filter to return a (minified) table of contents of the text.
     *
     * @param  string $content  The content to be filtered
     * @param  array  $params   Array of options for the tocify filter
     *
     * @return array            An array with a list of elements
     */
    public function tocifyFilter($content, $params = [])
    {
        // Resolve page and page content
        if ($content instanceof Page) {
            $page = $content;
            $content = $page->content();
        } else {
            $page = func_num_args() > 2 ? func_get_arg(2) : $this->grav['page'];
        }

        // Get custom user configuration
        $config = $this->mergeConfig($page, true, $params);
        $config->set('language', $page->language());

        // Just generate a table of contents for the current document
        return $this->init()->createToc($content, $config);
    }

    /**
     * Register {{% toc %}}, {{% minitoc %}} and {{% tocify %}} shortcodes.
     *
     * @param  Event  $event An event object
     */
    public function onShortcodesInitialized(Event $event)
    {
        $toc = $this->init();
        $function = function($event) {
            // Update header to bypass evaluation
            if (isset($event['page']->header()->toc->enabled)) {
                $event['page']->header()->toc->enabled = true;
            }

            return '[' . strtoupper($event['tag']) . ']';
        };

        $shortcodes = [
            new Shortcodes\InlineShortcode('toc', $function),
            new Shortcodes\InlineShortcode('minitoc', $function),
            // new Shortcodes\BlockShortcode('tocify', [$toc, 'tocifyShortcode'])
        ];

        // Register {{% toc %}}, {{% minitoc %}} and {{% tocify %}} shortcode
        $event['shortcodes']->register($shortcodes);
    }

    /**
     * Initialize plugin and all dependencies.
     *
     * @return \Grav\Plugin\Toc
     */
    protected function init()
    {
        static $instance = null;

        // Initialize Toc class
        if (!$instance) {
            require_once(__DIR__ . '/classes/Toc.php');
            require_once(__DIR__ . '/vendor/neitanod/forceutf8/src/ForceUTF8/Encoding.php');

            $instance = new Toc();
        }

        return $instance;
    }
}
