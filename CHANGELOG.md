# v1.3.1
## 10/24/2015

2. [](#improved)
  * Do not render TOC if it is empty. [#6](https://github.com/Sommerregen/grav-plugin-toc/issues/6) & [#7](https://github.com/Sommerregen/grav-plugin-toc/pull/7)
3. [](#bugfix)
  * Fixed [#5](https://github.com/Sommerregen/grav-plugin-toc/pull/5) (Fix typo in `README.md`)

# v1.3.0
## 09/24/2015

1. [](#new)
  * Added more blueprints for Grav Admin plugin
2. [](#improved)
  * Added configuration options for slug generation [#4](https://github.com/Sommerregen/grav-plugin-toc/issues/4)
  * Added better fallback for slug generation when `iconv` module is not installed on the server
3. [](#bugfix)
  * Fixed [#3](https://github.com/Sommerregen/grav-plugin-toc/issues/3) (Twig filter not working in twig template)

# v1.2.1
## 09/09/2015

2. [](#improved)
  * Added blueprints for Grav Admin plugin
  * Document PHP iconv Requirement [#1](https://github.com/Sommerregen/grav-plugin-toc/issues/1)
3. [](#bugfix)
  * Fixed [#2](https://github.com/Sommerregen/grav-plugin-toc/issues/2) (Not working with Grav's Admin Panel)
  * Fixed broken TOC after caching pages

# v1.2.0
## 08/08/2015

1. [](#new)
  * Added admin configurations **(requires Grav 0.9.34+)**
  * Added multi-language support **(requires Grav 0.9.33+)**
  * Added `placement`, `visible`, `icon` and `class` option to customize anchor look
  * Added buitlin CSS class to suppress anchor links with the `no-anchor` class
  * Added `{{% toc %}}` shortcode
2. [](#improved)
  * Switched to `onBuildPagesInitialized` event **(requires Grav 0.9.29+)**
  * Improved and use language translation for language specific slug generation (**requires Grav 0.9.34+**)
  * Updated `README.md`
3. [](#bugfix)
  * Strip tags in title attribute
  * Normalize tags in TOC (see `<code>` element)
  * Ignore empty headings

# v1.1.0
## 05/14/2015

2. [](#improved)
	* Improved `anchorlinks``generation
	* Truncate headings to a maximum width of 32 chars in TOC and MINITOC
	* Corrected spelling and markup in [README.md](https://github.com/Sommerregen/grav-plugin-toc/blob/master/README.md)

# v1.0.0
## 05/10/2015

1. [](#new)
  * ChangeLog started...
