<?php
/**
 * Plugin Name:       Research Agent Importer
 * Plugin URI:        https://github.com/your-username/research-agent
 * Description:       Imports AI-generated research articles from the Research Agent pipeline. Sets categories, SEO-optimised permalinks, meta tags, and Open Graph data automatically.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Research Agent
 * License:           MIT
 * Text Domain:       research-agent-importer
 */

defined( 'ABSPATH' ) || exit;

define( 'RAI_VERSION',     '1.0.0' );
define( 'RAI_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'RAI_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'RAI_OPTION_KEY',  'rai_settings' );
define( 'RAI_TOKEN_KEY',   'rai_api_token' );

// ── Autoload includes ─────────────────────────────────────────────────────────
require_once RAI_PLUGIN_DIR . 'includes/class-rai-markdown.php';
require_once RAI_PLUGIN_DIR . 'includes/class-rai-seo.php';
require_once RAI_PLUGIN_DIR . 'includes/class-rai-importer.php';
require_once RAI_PLUGIN_DIR . 'includes/class-rai-rest-api.php';
require_once RAI_PLUGIN_DIR . 'includes/class-rai-admin.php';

// ── Bootstrap ─────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', function () {
    RAI_Rest_API::init();
    RAI_Admin::init();
} );

// ── Activation: generate a secure API token ───────────────────────────────────
register_activation_hook( __FILE__, function () {
    if ( ! get_option( RAI_TOKEN_KEY ) ) {
        update_option( RAI_TOKEN_KEY, wp_generate_password( 48, false ) );
    }
    RAI_SEO::maybe_create_table();
} );
