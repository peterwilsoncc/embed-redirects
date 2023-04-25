<?php
/**
 * Test the rewrite rules.
 *
 * @package PWCC\EmbedRedirects\Tests
 */

namespace PWCC\EmbedRedirects\Tests;

use WP_UnitTestCase;

/**
 * Test the rewrite rules.
 */
class Test_Rewrite_Rules extends WP_UnitTestCase {
	/**
	 * Set up the test case.
	 */
	public function set_up() {
		parent::set_up();
		$this->set_permalink_structure( '/%year%/%monthnum%/%postname%/' );
		// Rewrite rules appear to be part of the default tear down.
		\PWCC\EmbedRedirects\rewrite_rules();
	}

	/**
	 * Ensure the rewrite rules are added.
	 */
	public function test_rewrite_rules() {
		$this->assertContains(
			'^open-redirect/([0-9a-zA-Z]+?)/?$',
			array_keys( get_option( 'rewrite_rules' ) )
		);
	}

	/**
	 * Ensure the query vars are added.
	 */
	public function test_query_vars() {
		$this->assertContains(
			'open-redirect',
			$GLOBALS['wp']->public_query_vars
		);

		$this->assertContains(
			'pwcc-er-checksum',
			$GLOBALS['wp']->public_query_vars
		);
	}

	/**
	 * Ensure the custom endpoint does not perform a query.
	 */
	public function test_custom_endpoint_does_not_perform_query() {
		$post_table_queries = 0;

		add_filter(
			'query',
			function( $query ) use ( &$post_table_queries ) {
				global $wpdb;
				if ( str_contains( $query, "FROM {$wpdb->posts}" ) ) {
					$post_table_queries++;
				}
				return $query;
			}
		);

		$this->go_to( '/open-redirect/1234567890/?open-redirect=http%3A%2F%2Fexample.org%2F' );
		$this->assertSame( $post_table_queries, 0 );
	}

	/**
	 * Ensure the custom endpoint does not redirect if the checksum is invalid.
	 */
	public function test_custom_endpoint_does_not_redirect_if_checksum_invalid() {
		$filter = new \MockAction();
		add_filter( 'wp_redirect', array( $filter, 'filter' ) );

		add_filter(
			'wp_redirect',
			function( $location ) {
				$this->fail( "wp_redirect() was called with location: {$location}" );
				// Prevent wp_redirect() from actually redirecting.
				return false;
			}
		);

		$this->go_to( '/open-redirect/1234567890/?open-redirect=http%3A%2F%2Fexample.org%2F' );
		$this->assertSame( $filter->get_call_count(), 0 );
	}

	/**
	 * Ensure the custom endpoint redirects if the checksum is valid.
	 */
	public function test_custom_endpoint_redirects_if_checksum_valid() {
		$actual = null;
		add_filter(
			'wp_redirect',
			function( $location ) use ( &$actual ) {
				$actual = $location;
				// Prevent wp_redirect() from actually redirecting.
				return false;
			}
		);

		$redirect = 'http://example.org/';
		$checksum = \PWCC\EmbedRedirects\create_checksum( $redirect );
		$this->go_to( "/open-redirect/{$checksum}/?open-redirect=" . rawurlencode( $redirect ) );
		$this->assertSame( $actual, $redirect );
	}
}
