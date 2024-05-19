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
	add_action( 'parse_request', __NAMESPACE__ . '\\parse_request' );
	add_filter( 'the_content', __NAMESPACE__ . '\\filter_the_content' );
}

/**
 * Activate plugin.
 *
 * Flush the rewrite rules during plugin activation.
 */
function activate_plugin() {
	// Runs late to ensure other plugin's rewrite rules are registered.
	add_action( 'init', 'flush_rewrite_rules', 1024 );
}

/**
 * Deactivate plugin.
 *
 * Flush the rewrite rules during plugin deactivation.
 */
function deactivate_plugin() {
	// Runs late to ensure other plugin's rewrite rules are registered.
	add_action( 'init', 'flush_rewrite_rules', 1024 );
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
 * - verified-redirect: The URL to redirect to.
 * - pwcc-er-checksum: The checksum to verify the redirect.
 *
 * One rewrite rule is created:
 * - verified-redirect/([0-9a-zA-Z]+?)/?$: The regex portion represents
 *   the checksum.
 *
 * @global \WP $wp WordPress request object.
 */
function rewrite_rules() {
	add_rewrite_tag( '%verified-redirect%', '([^&]+)', 'verified-redirect=' );
	add_rewrite_tag( '%pwcc-er-checksum%', '([^&]+)', 'pwcc-er-checksum=' );

	add_rewrite_rule(
		'^verified-redirect/([0-9a-zA-Z]+?)/?$',
		'index.php?pwcc-er-checksum=$matches[1]',
		'top'
	);

	global $wp;
	$wp->add_query_var( 'verified-redirect' );
	$wp->add_query_var( 'pwcc-er-checksum' );
}

/**
 * Parse the request.
 *
 * If the request is for an open redirect, validate the checksum and throw
 * a 404 error if it is invalid.
 *
 * If the checksum is valid, redirect on the send_headers action.
 *
 * @param \WP $wp WordPress request object.
 */
function parse_request( $wp ) {
	if (
		! isset( $wp->query_vars['verified-redirect'] )
		|| ! isset( $wp->query_vars['pwcc-er-checksum'] )
	) {
		return;
	}

	/*
	 * Prevent the main query from running.
	 *
	 * The verified-redirect query variable is not used to retrieve posts so the
	 * main query is not needed.
	 */
	add_filter( 'posts_pre_query', '__return_empty_array' );

	$redirect = $wp->query_vars['verified-redirect'];
	$checksum = $wp->query_vars['pwcc-er-checksum'];

	if (
		! sanitize_url( $redirect ) === $redirect
		|| ! validate_checksum( $redirect, $checksum )
	) {
		$wp->query_vars['error'] = '404';
		return;
	}

	add_action( 'send_headers', __NAMESPACE__ . '\\send_headers' );
}

/**
 * Send the redirect headers.
 */
function send_headers() {
	// Revalidate the url and checksum.
	$redirect = get_query_var( 'verified-redirect' );
	$checksum = get_query_var( 'pwcc-er-checksum' );

	if (
		! sanitize_url( $redirect ) === $redirect
		|| ! validate_checksum( $redirect, $checksum )
	) {
		return;
	}

	$default_redirect_code = 302;
	if ( in_array( wp_get_environment_type(), array( 'production', 'staging' ), true ) ) {
		$default_redirect_code = 301;
	}

	/**
	 * Filter the redirect code.
	 *
	 * Modify the redirect code used when redirecting to the destination URL.
	 * On production and staging environments, the default redirect code is
	 * 301 (permanent). On other environments, the default redirect code is
	 * 302 (temporary).
	 *
	 * WordPress Core includes a check to ensure the redirect code provided is
	 * correct and will trigger a fatal error if the code is not in the range
	 * 300-399. No validation is done by this plugin to ensure the filtered
	 * value is within that range.
	 *
	 * @param int $redirect_code Default redirect code.
	 */
	$redirect_code = apply_filters( 'pwcc_er_redirect_code', $default_redirect_code );

	/*
	 * Redirect to the destination URL.
	 *
	 * The use of a checksum is the safety mechanism to prevent open redirects.
	 * That allows the use of `wp_redirect()` rather than `wp_safe_redirect()`.
	 */
	// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
	wp_redirect( $redirect, $redirect_code, 'verified-redirect' );
	if ( class_exists( '\WP_UnitTestCase' ) ) {
		// Do not exit if running unit tests.
		return;
	}
	exit;
}

/**
 * Modify links for embeds.
 *
 * This replaces the links in the content with a redirect URL. The
 * default JavaScript only allows for redirects to the source site's
 * host so this resolves that by creating a redirect.
 *
 * @param string $content Post content.
 * @return string Updated post content.
 */
function filter_the_content( $content ) {
	// Only process content with links when viewing an embed.
	if ( ! str_contains( $content, 'href=' ) ) {
		return $content;
	}

	// Process HTML to find links.
	$dom = new \WP_HTML_Tag_Processor( $content );
	while ( $dom->next_tag( 'a' ) ) {
		$href = $dom->get_attribute( 'href' );
		if ( ! is_string( $href ) ) {
			continue;
		}

		// Check if the link is to a third party site.
		$host = wp_parse_url( $href, PHP_URL_HOST );
		if ( ! $host ) {
			continue;
		}

		if ( wp_parse_url( home_url(), PHP_URL_HOST ) === $host ) {
			continue;
		}

		// Only allow HTTP and HTTPS links.
		$scheme = wp_parse_url( $href, PHP_URL_SCHEME );
		if ( ! $scheme || ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			continue;
		}

		$checksum = create_checksum( $href );

		if ( get_option( 'permalink_structure' ) ) {
			$base_permalink = home_url( "verified-redirect/{$checksum}/" );
		} else {
			$base_permalink = add_query_arg(
				array(
					'pwcc-er-checksum' => $checksum,
				),
				home_url( '/' )
			);
		}

		// Create the redirect URL.
		$redirect = add_query_arg(
			array(
				'verified-redirect' => rawurlencode( $href ),
			),
			$base_permalink
		);

		// Replace the link with the redirect URL.
		$dom->set_attribute( 'href', esc_url( $redirect ) );
	}

	return $dom->get_updated_html();
}
