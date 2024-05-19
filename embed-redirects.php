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

require_once __DIR__ . '/inc/namespace.php';

bootstrap();
register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate_plugin' );
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate_plugin' );
