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

require_once __DIR__ . '/includes/class-wp-ai-builder-openai.php';
require_once __DIR__ . '/includes/class-wp-ai-builder-admin.php';

add_action( 'plugins_loaded', function () {
	if ( is_admin() ) {
		new WP_AI_Builder_Admin();
	}
} );
