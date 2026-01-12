<?php

class WP_AI_Builder_Admin {
	private $option_key = 'wp_ai_builder_settings';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_wp_ai_builder_preview', array( $this, 'handle_preview' ) );
		add_action( 'wp_ajax_wp_ai_builder_build', array( $this, 'handle_build' ) );
	}

	public function register_menu() {
		add_menu_page(
			'AI Website Builder',
			'AI Website Builder',
			'manage_options',
			'wp-ai-builder',
			array( $this, 'render_page' ),
			'dashicons-admin-site'
		);
	}

	public function register_settings() {
		register_setting( 'wp_ai_builder', $this->option_key, array( $this, 'sanitize_settings' ) );
	}

	public function sanitize_settings( $input ) {
		return array(
			'api_key' => isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '',
			'model' => isset( $input['model'] ) ? sanitize_text_field( $input['model'] ) : 'gpt-4o-mini',
		);
	}

	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_wp-ai-builder' !== $hook ) {
			return;
		}

		ob_start();
		settings_fields( 'wp_ai_builder' );
		$settings_fields = ob_get_clean();

		wp_enqueue_style(
			'wp-ai-builder-admin',
			plugin_dir_url( dirname( __DIR__ ) ) . 'assets/admin.css',
			array(),
			'0.2.0'
		);

		wp_enqueue_script(
			'wp-ai-builder-vue',
			'https://unpkg.com/vue@3/dist/vue.global.prod.js',
			array(),
			'3.4.38',
			true
		);

		wp_enqueue_script(
			'wp-ai-builder-admin-app',
			plugin_dir_url( dirname( __DIR__ ) ) . 'assets/admin-app.js',
			array( 'wp-ai-builder-vue' ),
			'0.2.0',
			true
		);

		wp_localize_script(
			'wp-ai-builder-admin-app',
			'wpAiBuilderSettings',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'wp_ai_builder_nonce' ),
				'preview' => get_option( 'wp_ai_builder_preview', '' ),
				'settingsFields' => $settings_fields,
			)
		);
	}

	public function render_page() {
		$settings = get_option( $this->option_key, array( 'api_key' => '', 'model' => 'gpt-4o-mini' ) );
		?>
		<div class="wrap wp-ai-builder-wrap">
			<div id="wp-ai-builder-app" data-api-key="<?php echo esc_attr( $settings['api_key'] ); ?>" data-model="<?php echo esc_attr( $settings['model'] ); ?>"></div>
			<noscript>
				<div class="notice notice-error"><p>JavaScript is required to use the AI Website Builder.</p></div>
			</noscript>
		</div>
		<?php
	}

	public function handle_preview() {
		check_ajax_referer( 'wp_ai_builder_nonce', 'nonce' );

		$settings = get_option( $this->option_key, array() );
		$api_key  = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
		$model    = isset( $settings['model'] ) ? $settings['model'] : 'gpt-4o-mini';

		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => 'Please configure an OpenAI API key first.' ) );
		}

		$data = $this->sanitize_brief( $_POST );

		$prompt = $this->build_preview_prompt( $data );
		$result = WP_AI_Builder_OpenAI::request( $prompt, $api_key, $model );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$preview = wp_kses_post( $result );
		update_option( 'wp_ai_builder_preview', $preview );

		wp_send_json_success( array( 'html' => $preview ) );
	}

	public function handle_build() {
		check_ajax_referer( 'wp_ai_builder_nonce', 'nonce' );

		$settings = get_option( $this->option_key, array() );
		$api_key  = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
		$model    = isset( $settings['model'] ) ? $settings['model'] : 'gpt-4o-mini';

		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => 'Please configure an OpenAI API key first.' ) );
		}

		$data   = $this->sanitize_brief( $_POST );
		$pages  = array_filter( array_map( 'trim', explode( ',', $data['pages'] ) ) );
		if ( empty( $pages ) ) {
			$pages = array( 'Home', 'About', 'Services', 'Contact' );
		}
		$prompt = $this->build_page_prompt( $data );

		$created_pages = array();

		foreach ( $pages as $page_title ) {
			$page_prompt = $prompt . "\n\nGenerate content for the page titled '{$page_title}' in HTML.";
			$content     = WP_AI_Builder_OpenAI::request( $page_prompt, $api_key, $model );

			if ( is_wp_error( $content ) ) {
				wp_send_json_error( array( 'message' => $content->get_error_message() ) );
			}

			$page_id = wp_insert_post(
				array(
					'post_title' => $page_title,
					'post_content' => wp_kses_post( $content ),
					'post_status' => 'publish',
					'post_type' => 'page',
				)
			);

			if ( $page_id && ! is_wp_error( $page_id ) ) {
				$created_pages[] = $page_title;
			}
		}

		$this->create_theme( $data );

		wp_send_json_success(
			array(
				'message' => 'Website created successfully.',
				'pages' => $created_pages,
			)
		);
	}

	private function sanitize_brief( $data ) {
		return array(
			'sector' => isset( $data['sector'] ) ? sanitize_text_field( $data['sector'] ) : '',
			'logo' => isset( $data['logo'] ) ? esc_url_raw( $data['logo'] ) : '',
			'colors' => isset( $data['colors'] ) ? sanitize_text_field( $data['colors'] ) : '',
			'site_type' => isset( $data['siteType'] ) ? sanitize_text_field( $data['siteType'] ) : '',
			'pages' => isset( $data['pages'] ) ? sanitize_text_field( $data['pages'] ) : '',
			'notes' => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '',
		);
	}

	private function build_preview_prompt( $data ) {
		return sprintf(
			"Create a homepage preview in HTML with inline styles. Sector: %s. Website type: %s. Brand colors: %s. Logo URL: %s. Notes: %s. Return only the HTML body content without markdown.",
			$data['sector'],
			$data['site_type'],
			$data['colors'],
			$data['logo'],
			$data['notes']
		);
	}

	private function build_page_prompt( $data ) {
		return sprintf(
			"You are building a full WordPress site. Sector: %s. Website type: %s. Brand colors: %s. Logo URL: %s. Notes: %s. Use friendly marketing copy, include calls to action, and return only HTML body content.",
			$data['sector'],
			$data['site_type'],
			$data['colors'],
			$data['logo'],
			$data['notes']
		);
	}

	private function create_theme( $data ) {
		$theme_slug = 'ai-builder-theme';
		$theme_dir  = trailingslashit( WP_CONTENT_DIR ) . 'themes/' . $theme_slug;

		if ( ! file_exists( $theme_dir ) ) {
			wp_mkdir_p( $theme_dir );
		}

		$style_css = "/*\nTheme Name: AI Builder Theme\nVersion: 0.1.0\n*/\n\n:root{--primary-color:#0f172a;--accent-color:#38bdf8;}\nbody{font-family:system-ui, sans-serif; margin:0; color:#0f172a; background:#f8fafc;}\nheader{padding:24px; background:var(--primary-color); color:#fff;}\nheader img{max-height:48px; vertical-align:middle; margin-right:16px;}\nmain{padding:32px;}\nsection{margin-bottom:32px;}\n.button{display:inline-block; padding:12px 20px; background:var(--accent-color); color:#0f172a; text-decoration:none; border-radius:6px; font-weight:600;}\n";

		if ( ! empty( $data['colors'] ) ) {
			$colors    = array_map( 'trim', explode( ',', $data['colors'] ) );
			$primary   = $colors[0] ?? '#0f172a';
			$secondary = $colors[1] ?? '#38bdf8';
			$style_css .= ":root{--primary-color:{$primary};--accent-color:{$secondary};}";
		}

		$header_php = "<?php ?>\n<!doctype html>\n<html <?php language_attributes(); ?>>\n<head>\n<meta charset=\"<?php bloginfo( 'charset' ); ?>\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n<?php wp_head(); ?>\n</head>\n<body <?php body_class(); ?>>\n<header>\n<div class=\"site-branding\">";
		if ( ! empty( $data['logo'] ) ) {
			$header_php .= "<img src=\"" . esc_url( $data['logo'] ) . "\" alt=\"Logo\">";
		}
		$header_php .= "<span class=\"site-title\"><?php bloginfo( 'name' ); ?></span>\n</div>\n</header>\n<main>";

		$footer_php = "</main>\n<footer style=\"padding:24px; text-align:center; background:#e2e8f0;\">\n<p>&copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></p>\n</footer>\n<?php wp_footer(); ?>\n</body>\n</html>";

		$index_php = "<?php get_header(); ?>\n<?php if ( have_posts() ) : ?>\n<?php while ( have_posts() ) : the_post(); ?>\n<article id=\"post-<?php the_ID(); ?>\">\n<?php the_content(); ?>\n</article>\n<?php endwhile; ?>\n<?php endif; ?>\n<?php get_footer(); ?>";

		file_put_contents( $theme_dir . '/style.css', $style_css );
		file_put_contents( $theme_dir . '/functions.php', "<?php\nadd_action( 'wp_enqueue_scripts', function() {\n\twp_enqueue_style( 'ai-builder-theme', get_stylesheet_uri(), array(), '0.1.0' );\n} );\n" );
		file_put_contents( $theme_dir . '/header.php', $header_php );
		file_put_contents( $theme_dir . '/footer.php', $footer_php );
		file_put_contents( $theme_dir . '/index.php', $index_php );
		file_put_contents( $theme_dir . '/page.php', $index_php );

		switch_theme( $theme_slug );
	}
}
