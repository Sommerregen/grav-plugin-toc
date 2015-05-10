<?php
/**
 * Toc v1.0.0
 *
 * This plugin automagically generates a (minified) Table of Contents
 * based on special markers in the document and adds it into the
 * resulting HTML document.
 *
 * Licensed under MIT, see LICENSE.
 *
 * @package     Toc
 * @version     1.0.0
 * @link        <https://github.com/sommerregen/grav-plugin-external-links>
 * @author      Benjamin Regler <sommerregen@benjamin-regler.de>
 * @copyright   2015, Benjamin Regler
 * @license     <http://opensource.org/licenses/MIT>            MIT
 */

namespace Grav\Plugin;

use Grav\Common\Grav;
use Grav\Common\Plugin;
use Grav\Common\Data\Data;
use Grav\Common\Page\Page;
use RocketTheme\Toolbox\Event\Event;

use Grav\Plugin\Toc\Toc;

/**
 * Toc
 *
 * This plugin automagically generates a (minified) Table of Contents
 * based on special markers in the document and adds it into the
 * resulting HTML document.
 */
class TocPlugin extends Plugin
{
  /** ---------------------------
   * Private/protected properties
   * ----------------------------
   */

  /**
   * Instance of Toc class
   *
   * @var object
   */
  protected $toc;

  /** -------------
   * Public methods
   * --------------
   */

  /**
   * Return a list of subscribed events.
   *
   * @return array    The list of events of the plugin of the form
   *                      'name' => ['method_name', priority].
   */
  public static function getSubscribedEvents() {
    return [
      'onPluginsInitialized' => ['onPluginsInitialized', 0],
    ];
  }

  /**
   * Initialize configuration.
   */
  public function onPluginsInitialized() {
    if ($this->isAdmin()) {
      $this->active = false;
      return;
    }

    if ( $this->config->get('plugins.toc.enabled') ) {
      // Initialize Toc class
      require_once(__DIR__.'/classes/Toc.php');
      $this->toc = new Toc();

      $this->enable([
        'onPageContentProcessed' => ['onPageContentProcessed', 0],
        'onTwigInitialized' => ['onTwigInitialized', 0],
        'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
        'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
      ]);
    }
  }

  /**
   * Initialize Twig configuration and filters.
   */
  public function onTwigInitialized()
  {
    // Expose tocFilter
    $this->grav['twig']->twig()->addFilter(
      new \Twig_SimpleFilter('toc', [$this, 'TocFilter'], ['is_safe' => ['html']])
    );
  }

  /**
   * Add current directory to Twig lookup paths.
   */
  public function onTwigTemplatePaths()
  {
    $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
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
    if ($config->get('enabled', false)) {
      // Get content, apply TocFilter and save modified page content
      $content = $page->getRawContent();
      $page->setRawContent($this->tocFilter($content));
    }
  }

  /**
   * Set needed variables to display a table of contents.
   */
  public function onTwigSiteVariables() {
    if ($this->config->get('plugins.toc.built_in_css')) {
      $this->grav['assets']->add('plugin://toc/assets/css/toc.css');
    }
  }

  /**
   * Filter to automatically create a (minified) table of contents.
   *
   * @param  string $content The content to be filtered
   * @param  array  $options Array of options for the TOC filter
   *
   * @return string          The content with inserted anchor- and
   *                         permalinks in headings and table of contents
   *                         blocks
   */
  public function tocFilter($content, $params = [])
  {
    // Get custom user configuration
    $config = $this->mergeConfig($this->grav['page'], $params);

    // Process content (apply TOC filter)
    return $this->toc->process($content, $config);
  }

  /** -------------------------------
   * Private/protected helper methods
   * --------------------------------
   */

  /**
   * Merge global and page configurations.
   *
   * @param Page  $page    The page to merge the configurations with the
   *                       plugin settings.
   * @param array $params  Array of additional configuration options to
   *                       merge with the plugin settings.
   *
   * @param bool  $deep    Should you use deep or shallow merging
   *
   * @return \Grav\Common\Data\Data
   */
  protected function mergeConfig(Page $page, $params = [], $deep = false)
  {
    $config = parent::mergeConfig($page, $deep);
    $header = $page->header();

    // Check whether this plugin is enabled or disabled via page header
    if (isset($header->{$this->name})) {
      if (is_bool($header->{$this->name})) {
        // Overwrite boolean value
        $config->set('enabled', $header->{$this->name});
      }
    }

    // Merge additional parameter for configuration options
    if (count($params)) {
      $config->merge($params);
    }

    // Return modified config
    return $config;
  }
}
