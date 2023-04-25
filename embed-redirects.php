<?php
/**
 * Embed Redirects
 *
 * @package           EmbedRedirects
 * @author            Peter Wilson
 * @copyright         2023 Peter Wilson
 * @license           MIT
 *
 * @wordpress-plugin
 * Plugin Name: Embed Redirects
 * Description: Allow links to third party websites in embeds via a redirect.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Peter Wilson
 * Author URI: https://peterwilson.cc
 * License: MIT
 * Text Domain: embed-redirects
 */

namespace PWCC\EmbedRedirects;

/**
 * Only warnings now.
 */
function coding_standards_failures() {
	$array = array(
		'mis' => 'aligned',
		'to' => 'trigger warning',
	);
}
