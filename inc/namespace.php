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
 * Create a checksum for a URL.
 *
 * Checksums differ from a nonce in that the same URL will always use the
 * same value, regardless of the user. The purpose of the checksum is to
 * validate that the URL is safe to redirect to.
 *
 * For the purpose of hashing, the `nonce` scheme is used as it's the
 * best match for the purpose.
 *
 * @param string $url Destination URL.
 * @return string Checksum.
 */
function create_checksum( $url ) {
	return wp_hash( $url, 'nonce' );
}

/**
 * Validate a checksum for a URL.
 *
 * @param string $url      Destination URL.
 * @param string $checksum Checksum to validate.
 * @return bool True if the checksum is valid, false otherwise.
 */
function validate_checksum( $url, $checksum ) {
	// Checksum is case insensitive.
	$checksum = strtolower( $checksum );
	return hash_equals( $checksum, create_checksum( $url ) );
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
