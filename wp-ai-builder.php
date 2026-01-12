<?php
/**
 * Plugin Name: WP AI Builder
 * Description: Build AI-generated WordPress websites from structured instructions with OpenAI.
 * Version: 0.1.0
 * Author: WP AI Builder
 * License: GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_AI_BUILDER_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_AI_BUILDER_URL', plugin_dir_url( __FILE__ ) );

require_once WP_AI_BUILDER_PATH . 'includes/class-wp-ai-builder-openai.php';
require_once WP_AI_BUILDER_PATH . 'includes/class-wp-ai-builder-admin.php';

add_action( 'plugins_loaded', function () {
	if ( is_admin() ) {
		new WP_AI_Builder_Admin();
	}
} );
