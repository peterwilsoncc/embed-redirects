=== Embed Redirects ===
Contributors: peterwilsoncc
Tags: redirects, embeds
Requires at least: 6.6
Tested up to: 6.8
Stable tag: 1.0.0
Requires PHP: 8.0
License: MIT
License URI: https://github.com/peterwilsoncc/embed-redirects/blob/main/LICENSE

Allow links to third party websites in embeds via a redirect.

== Description ==
By default, WordPress prevents embedded content from containing links to sites other than the source of the redirect.

While this is fine for the standard WordPress embeds. For sites that customize the embed template to display the full content of the post it will prevent links to third party sites working when the post is embedded on another site.

This allows the use of links to third party sites in the content by creating a redirect endpoint in WordPress for redirecting to other sites. To avoid an open redirect, it validates the URL to ensure the redirect is permitted by the site doing the redirect.

This plugin needs to run on the site with the modified embed template. For sites using the embed the plugin does not have any effect.

== Frequently Asked Questions ==
= Where is this plugin's menu page? =

There isn't one, the plugin doesn't have any options to configure so works upon activation.

= The redirects aren't working =

The plugin attempts to flush the WordPress rewrite rules during activation. If this failed for some reason then visit the WordPress Dashboard > Settings > Permalinks page and see if that helps.

== Changelog ==
= 1.0 =

Initial release.
