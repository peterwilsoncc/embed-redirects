<?php
/**
 * Test the rewrite rules.
 *
 * @package PWCC\EmbedRedirects\Tests
 */

namespace PWCC\EmbedRedirects\Tests;

use WP_UnitTestCase;
use WP_UnitTest_Factory;

/**
 * Test the rewrite rules.
 */
class Test_Rewrite_Rules extends WP_UnitTestCase {
	/**
	 * Shared post ID.
	 *
	 * @var int
	 */
	public static $post_id;

	/**
	 * Set up shared fixture.
	 *
	 * @param WP_UnitTest_Factory $factory Factory object.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create();
	}

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
			'^verified-redirect/([0-9a-zA-Z]+?)/?$',
			array_keys( get_option( 'rewrite_rules' ) )
		);
	}

	/**
	 * Ensure the query vars are added.
	 */
	public function test_query_vars() {
		$this->assertContains(
			'verified-redirect',
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
			function ( $query ) use ( &$post_table_queries ) {
				global $wpdb;
				if ( str_contains( $query, "FROM {$wpdb->posts}" ) ) {
					$post_table_queries++;
				}
				return $query;
			}
		);

		$this->go_to( '/verified-redirect/1234567890/?verified-redirect=http%3A%2F%2Fshuckedmusical.com%2F' );
		$this->assertSame( 0, $post_table_queries );
	}

	/**
	 * Ensure the custom endpoint does not redirect if the checksum is invalid.
	 */
	public function test_custom_endpoint_does_not_redirect_if_checksum_invalid() {
		$filter = new \MockAction();
		add_filter( 'wp_redirect', array( $filter, 'filter' ) );

		add_filter(
			'wp_redirect',
			function ( $location ) {
				$this->fail( "wp_redirect() was called with location: {$location}" );
				// Prevent wp_redirect() from actually redirecting.
				return false;
			}
		);

		$this->go_to( '/verified-redirect/1234567890/?verified-redirect=http%3A%2F%2Fshuckedmusical.com%2F' );
		$this->assertSame( 0, $filter->get_call_count() );
	}

	/**
	 * Ensure the custom endpoint does not redirect if the checksum is invalid using plain permalink.
	 */
	public function test_custom_endpoint_does_not_redirect_if_checksum_invalid_using_plain_permalink() {
		$filter = new \MockAction();
		add_filter( 'wp_redirect', array( $filter, 'filter' ) );

		add_filter(
			'wp_redirect',
			function ( $location ) {
				$this->fail( "wp_redirect() was called with location: {$location}" );
				// Prevent wp_redirect() from actually redirecting.
				return false;
			}
		);

		$this->go_to( '/?pwcc-er-checksum=1234567890&verified-redirect=http%3A%2F%2Fshuckedmusical.com%2F' );
		$this->assertSame( 0, $filter->get_call_count() );
	}

	/**
	 * Ensure the custom endpoint redirects if the checksum is valid.
	 */
	public function test_custom_endpoint_redirects_if_checksum_valid() {
		$actual = null;
		add_filter(
			'wp_redirect',
			function ( $location ) use ( &$actual ) {
				$actual = $location;
				// Prevent wp_redirect() from actually redirecting.
				return false;
			}
		);

		$redirect = 'https://shuckedmusical.com/';
		$checksum = \PWCC\EmbedRedirects\create_checksum( $redirect );
		$this->go_to( "/verified-redirect/{$checksum}/?verified-redirect=" . rawurlencode( $redirect ) );
		$this->assertSame( $redirect, $actual );
	}

	/**
	 * Ensure the custom endpoint redirects if the checksum is valid using plain permalink.
	 */
	public function test_custom_endpoint_redirects_if_checksum_valid_using_plain_permalink() {
		$actual = null;
		add_filter(
			'wp_redirect',
			function ( $location ) use ( &$actual ) {
				$actual = $location;
				// Prevent wp_redirect() from actually redirecting.
				return false;
			}
		);

		$redirect = 'https://shuckedmusical.com/';
		$checksum = \PWCC\EmbedRedirects\create_checksum( $redirect );
		$this->go_to( "?pwcc-er-checksum={$checksum}&verified-redirect=" . rawurlencode( $redirect ) );
		$this->assertSame( $redirect, $actual );
	}

	/**
	 * Ensure plain permalinks are used for sites without rewrite rules.
	 */
	public function test_plain_permalink_structure() {
		$this->set_permalink_structure( '' );
		$link     = 'http://shuckedmusical.com/';
		$content  = "<a href=\"{$link}\">Link</a>";
		$checksum = \PWCC\EmbedRedirects\create_checksum( $link );

		// Set up the conditionals so is_embed is true.
		$this->go_to( get_post_embed_url( self::$post_id ) );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- testing WP hook.
		$filtered_content = apply_filters( 'the_content', $content );

		$this->assertTrue( is_embed() );
		$this->assertStringContainsString( 'href="' . esc_url( home_url( "/?pwcc-er-checksum={$checksum}&verified-redirect=" . rawurlencode( $link ) ) ) . '"', $filtered_content );
	}

	/**
	 * Ensure the content is updated for valid links.
	 *
	 * @dataProvider data_content_updated_for_valid_links
	 *
	 * @param string $link Link to test.
	 */
	public function test_content_updated_for_valid_links( $link ) {
		$content  = "<a href=\"{$link}\">Link</a>";
		$checksum = \PWCC\EmbedRedirects\create_checksum( $link );

		// Set up the conditionals so is_embed is true.
		$this->go_to( get_post_embed_url( self::$post_id ) );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- testing WP hook.
		$filtered_content = apply_filters( 'the_content', $content );

		$this->assertTrue( is_embed() );
		$this->assertStringContainsString( 'href="' . home_url( "/verified-redirect/{$checksum}/?verified-redirect=" . rawurlencode( $link ) ) . '"', $filtered_content );
	}

	/**
	 * Data provider for test_content_updated_for_valid_links.
	 *
	 * @return array[]
	 */
	public function data_content_updated_for_valid_links() {
		return array(
			'http URL'  => array( 'http://shuckedmusical.com/' ),
			'https URL' => array( 'https://shuckedmusical.com/' ),
		);
	}

	/**
	 * Ensure the content is not updated for invalid links.
	 *
	 * @dataProvider data_content_is_not_updated_for_invalid_links
	 *
	 * @param string $link Link to test.
	 */
	public function test_content_is_not_updated_for_invalid_links( $link ) {
		$content  = "<a href=\"{$link}\">Link</a>";
		$checksum = \PWCC\EmbedRedirects\create_checksum( $link );

		// Set up the conditionals so is_embed is true.
		$this->go_to( get_post_embed_url( self::$post_id ) );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- testing WP hook.
		$filtered_content = apply_filters( 'the_content', $content );

		$this->assertTrue( is_embed() );
		$this->assertStringNotContainsString( 'href="' . home_url( "/verified-redirect/{$checksum}/?verified-redirect=" . rawurlencode( $link ) ) . '"', $filtered_content );
		$this->assertStringContainsString( $content, $filtered_content );
	}

	/**
	 * Data provider for test_content_is_not_updated_for_invalid_links.
	 *
	 * @return array[]
	 */
	public function data_content_is_not_updated_for_invalid_links() {
		return array(
			'No scheme'         => array( 'shuckedmusical.com/' ),
			'FTP URL'           => array( 'ftp://shuckedmusical.com/' ),
			'Mailto URL'        => array( 'mailto:bounce@shuckedmusical.com' ),
			'JavaScript URL'    => array( 'javascript:alert("Hello, World!");' ),
			'Telephone URL'     => array( 'tel:+1234567890' ),
			'Data URL'          => array( 'data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==' ),
			'Empty URL'         => array( '' ),
			'Relative URL'      => array( '../index.html' ),
			'Absolute path URL' => array( '/index.html' ),
			'Anchor URL'        => array( '#top' ),
			'Query URL'         => array( '?query=string' ),
		);
	}


	/**
	 * Ensure the content is not updated for local/site links.
	 *
	 * @dataProvider data_content_is_not_updated_for_local_urls
	 *
	 * @param string $link Link to test.
	 */
	public function test_content_is_not_updated_for_local_urls( $link ) {
		$content  = "<a href=\"{$link}\">Link</a>";
		$checksum = \PWCC\EmbedRedirects\create_checksum( $link );

		// Set up the conditionals so is_embed is true.
		$this->go_to( get_post_embed_url( self::$post_id ) );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- testing WP hook.
		$filtered_content = apply_filters( 'the_content', $content );

		$this->assertTrue( is_embed() );
		$this->assertStringNotContainsString( 'href="' . home_url( "/verified-redirect/{$checksum}/?verified-redirect=" . rawurlencode( $link ) ) . '"', $filtered_content );
		$this->assertStringContainsString( $content, $filtered_content );
	}

	/**
	 * Data provider for test_content_is_not_updated_for_local_urls.
	 *
	 * @return array[]
	 */
	public function data_content_is_not_updated_for_local_urls() {
		return array(
			'http URL'  => array( set_url_scheme( home_url( '/example/' ), 'http' ) ),
			'https URL' => array( set_url_scheme( home_url( '/example/' ), 'https' ) ),
		);
	}

	/**
	 * Ensure the content is not changed for an empty href attribute.
	 */
	public function test_content_not_changed_for_empty_href() {
		$content = '<a href>Link</a>';

		// Set up the conditionals so is_embed is true.
		$this->go_to( get_post_embed_url( self::$post_id ) );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- testing WP hook.
		$filtered_content = apply_filters( 'the_content', $content );

		$this->assertTrue( is_embed() );
		$this->assertStringNotContainsString( 'href="' . home_url( '/verified-redirect/' ), $filtered_content );
		$this->assertStringContainsString( $content, $filtered_content );
	}
}
