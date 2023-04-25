<?php
/**
 * Embed Redirects
 *
 * @package           EmbedRedirects
 * @author            Peter Wilson
 * @copyright         2023 Peter Wilson
 * @license           MIT
 */

namespace PWCC\EmbedRedirects;

/**
 * Bootstrap the plugin.
 */
function bootstrap() {
	add_action( 'init', __NAMESPACE__ . '\\rewrite_rules' );
}

/**
 * Add rewrite rules.
 *
 * Create the rewrite rule for enabling redirects.
 *
 * Two rewrite tags & query variables are created:
 * - open-redirect: The URL to redirect to.
 * - pwcc-er-checksum: The checksum to verify the redirect.
 *
 * One rewrite rule is created:
 * - open-redirect/([0-9a-zA-Z]+?)/?$: The regex portion represents
 *   the checksum.
 *
 * @global \WP $wp WordPress request object.
 */
function rewrite_rules() {
	add_rewrite_tag( '%open-redirect%', '([^&]+)', 'open-redirect=' );
	add_rewrite_tag( '%pwcc-er-checksum%', '([^&]+)', 'pwcc-er-checksum=' );

	add_rewrite_rule(
		'^open-redirect/([0-9a-zA-Z]+?)/?$',
		'index.php?pwcc-er-checksum=$matches[1]',
		'top'
	);

	global $wp;
	$wp->add_query_var( 'open-redirect' );
	$wp->add_query_var( 'pwcc-er-checksum' );
}
