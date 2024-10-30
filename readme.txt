=== Header Watermark ===
Contributors: agnu
Tags: header, watermark, random, image
Requires at least: 3.0.0
Tested up to: 3.2.1
Stable tag: 1.0.1

Add a watermark image to your WordPress header image and use suitable images from your media library as the header image(s).

== Description ==

Header Watermark provides you with extra flexibility and control over the header image(s) for your WordPress site:

* Use any image from your Media Library as a watermark for your header image(s)
* Control the placement and opacity of the watermark
* Select any suitable image from your Media Library as your header image(s)
* Control which images are used for random header images

== Requirements ==
Header Watermark requires the following to work:

* GD Library PHP extension - version 2.0.28 or higher
* The WordPress theme must use the *get_header_image()* function to generate the header image and must also define the HEADER_IMAGE_WIDTH and HEADER_IMAGE_HEIGHT constants

== Themes ==

This plugin works with the Twenty Ten theme with no further configuration.

If you use this plugin with the Twenty Eleven theme, you may see a notice at the top of your website/WordPress admin pages like this:

> Notice: Constant HEADER_IMAGE already defined in /var/www/yoursite/wordpress/wp-content/themes/twentyeleven/functions.php on line 118

You may also find that you get occasional errors on some WordPress admin pages, for example if you activate a different theme.

To prevent the notice and errors, find and change the following line in the *functions.php* file of the Twenty Eleven theme:

Change this:
`define( 'HEADER_IMAGE', '' );`
to this:
`if ( ! defined( 'HEADER_IMAGE' ) ) {
  define( 'HEADER_IMAGE', '' );
}`

Any other themes that define the HEADER_IMAGE constant may have the same problems and need to be altered in the same way for the plugin to work.

== Installation ==

1. Install Header Watermark either via the WordPress.org plugin directory, or upload the *header-watermark* directory to the */wp-content/plugins/* directory
1. Activate the plugin through the *Plugins* menu in WordPress
1. Select the *Header Watermark* item in the *Appearance* menu to configure the plugin

== Screenshots ==

1. Administration interface

== Changelog ==

= 1.0.1 =
* Fixed a bug caused by wrong plugin directory reference.
