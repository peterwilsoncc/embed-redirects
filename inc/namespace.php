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
		! isset( $wp->query_vars['open-redirect'] )
		|| ! isset( $wp->query_vars['pwcc-er-checksum'] )
	) {
		return;
	}

	/*
	 * Prevent the main query from running.
	 *
	 * The open-redirect query variable is not used to retrieve posts so the
	 * main query is not needed.
	 */
	add_filter( 'posts_pre_query', '__return_empty_array' );

	$redirect = $wp->query_vars['open-redirect'];
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
	$redirect = get_query_var( 'open-redirect' );
	$checksum = get_query_var( 'pwcc-er-checksum' );

	if (
		! sanitize_url( $redirect ) === $redirect
		|| ! validate_checksum( $redirect, $checksum )
	) {
		return;
	}

	/*
	 * Redirect to the destination URL.
	 *
	 * The use of a checksum is the safety mechanism to prevent open redirects.
	 * That allows the use of `wp_redirect()` rather than `wp_safe_redirect()`.
	 */
	// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
	wp_redirect( $redirect, 302, 'Open-Redirect' );
	exit;
}
