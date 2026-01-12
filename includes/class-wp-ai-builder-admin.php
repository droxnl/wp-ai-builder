<?php

if ( ! class_exists( 'WP_AI_Builder_Admin' ) ) {
class WP_AI_Builder_Admin {
	private $option_key = 'wp_ai_builder_settings';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_wp_ai_builder_preview', array( $this, 'handle_preview' ) );
		add_action( 'wp_ajax_wp_ai_builder_build', array( $this, 'handle_build' ) );
		add_action( 'wp_ajax_wp_ai_builder_suggest', array( $this, 'handle_suggestions' ) );
		add_action( 'wp_ajax_wp_ai_builder_prompt', array( $this, 'handle_prompt_builder' ) );
		add_action( 'wp_ajax_wp_ai_builder_logs', array( $this, 'handle_logs' ) );
		add_action( 'wp_ajax_wp_ai_builder_cleanup', array( $this, 'handle_cleanup' ) );
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

		add_submenu_page(
			'wp-ai-builder',
			'AI Website Builder - Instellingen',
			'Instellingen',
			'manage_options',
			'wp-ai-builder-settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting( 'wp_ai_builder', $this->option_key, array( $this, 'sanitize_settings' ) );
	}

	public function sanitize_settings( $input ) {
		return array(
			'api_key' => isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '',
			'model' => isset( $input['model'] ) ? sanitize_text_field( $input['model'] ) : 'gpt-4o-mini',
			'pexels_api_key' => isset( $input['pexels_api_key'] ) ? sanitize_text_field( $input['pexels_api_key'] ) : '',
		);
	}

	public function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, array( 'toplevel_page_wp-ai-builder', 'ai-website-builder_page_wp-ai-builder-settings' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'wp-ai-builder-admin',
			WP_AI_BUILDER_URL . 'assets/admin.css',
			array(),
			'0.3.0'
		);

		if ( 'toplevel_page_wp-ai-builder' !== $hook ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_script(
			'wp-ai-builder-vue',
			'https://unpkg.com/vue@3/dist/vue.global.prod.js',
			array(),
			'3.4.38',
			true
		);

		wp_enqueue_script(
			'wp-ai-builder-admin-app',
			WP_AI_BUILDER_URL . 'assets/admin-app.js',
			array( 'wp-ai-builder-vue' ),
			'0.3.0',
			true
		);

		$settings = $this->get_settings();

		wp_localize_script(
			'wp-ai-builder-admin-app',
			'wpAiBuilderSettings',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'wp_ai_builder_nonce' ),
				'preview' => get_option( 'wp_ai_builder_preview', '' ),
				'hasApiKey' => ! empty( $settings['api_key'] ),
				'hasPexelsKey' => ! empty( $settings['pexels_api_key'] ),
			)
		);
	}

	public function render_page() {
		?>
		<div class="wrap wp-ai-builder-wrap">
			<div id="wp-ai-builder-app"></div>
			<noscript>
				<div class="notice notice-error"><p>JavaScript is vereist om de AI Website Builder te gebruiken.</p></div>
			</noscript>
		</div>
		<?php
	}

	public function render_settings_page() {
		$settings = $this->get_settings();
		?>
		<div class="wrap wp-ai-builder-settings">
			<h1>AI Website Builder instellingen</h1>
			<p class="description">Beheer de API sleutels die nodig zijn om previews, content en afbeeldingen te genereren.</p>
			<form method="post" action="options.php" class="wp-ai-builder-settings__form">
				<?php settings_fields( 'wp_ai_builder' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wp-ai-builder-api-key">OpenAI API key</label></th>
						<td><input id="wp-ai-builder-api-key" type="password" name="<?php echo esc_attr( $this->option_key ); ?>[api_key]" value="<?php echo esc_attr( $settings['api_key'] ); ?>" class="regular-text" placeholder="sk-..." /></td>
					</tr>
					<tr>
						<th scope="row"><label for="wp-ai-builder-model">OpenAI model</label></th>
						<td><input id="wp-ai-builder-model" type="text" name="<?php echo esc_attr( $this->option_key ); ?>[model]" value="<?php echo esc_attr( $settings['model'] ); ?>" class="regular-text" placeholder="gpt-4o-mini" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="wp-ai-builder-pexels">Pexels API key</label></th>
						<td>
							<input id="wp-ai-builder-pexels" type="password" name="<?php echo esc_attr( $this->option_key ); ?>[pexels_api_key]" value="<?php echo esc_attr( $settings['pexels_api_key'] ); ?>" class="regular-text" placeholder="Pexels API key" />
							<p class="description">Gebruik de Pexels API om afbeeldingen in previews en pagina's te vullen.</p>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Instellingen opslaan' ); ?>
			</form>
		</div>
		<?php
	}

	public function handle_preview() {
		check_ajax_referer( 'wp_ai_builder_nonce', 'nonce' );

		$settings = $this->get_settings();
		$api_key  = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
		$model    = isset( $settings['model'] ) ? $settings['model'] : 'gpt-4o-mini';

		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => 'Vul eerst een OpenAI API key in bij Instellingen.' ) );
		}

		$this->log_event( 'Preview wordt voorbereid.' );
		$data = $this->sanitize_brief( $_POST );
		$data = $this->attach_logo_data( $data, false );
		$data['pexels_images'] = $this->get_pexels_images( $data, $settings );
		if ( ! empty( $data['pexels_images'] ) ) {
			$this->log_event( 'Pexels beelden toegevoegd aan de preview.' );
		}

		$prompt = $this->build_preview_prompt( $data );
		$result = WP_AI_Builder_OpenAI::request( $prompt, $api_key, $model );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$preview = wp_kses_post( $result );
		update_option( 'wp_ai_builder_preview', $preview );
		$this->log_event( 'Preview succesvol gegenereerd.' );

		wp_send_json_success( array( 'html' => $preview, 'logs' => $this->get_logs() ) );
	}

	public function handle_build() {
		check_ajax_referer( 'wp_ai_builder_nonce', 'nonce' );

		$settings = $this->get_settings();
		$api_key  = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
		$model    = isset( $settings['model'] ) ? $settings['model'] : 'gpt-4o-mini';

		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => 'Vul eerst een OpenAI API key in bij Instellingen.' ) );
		}

		$this->log_event( 'Start met bouwen van de website.' );
		$data   = $this->sanitize_brief( $_POST );
		$data   = $this->attach_logo_data( $data, true );
		$data['pexels_images'] = $this->get_pexels_images( $data, $settings );
		$data['pexels_attachments'] = $this->sideload_pexels_images( $data['pexels_images'] );
		$data['pexels_attachment_map'] = $this->map_attachment_urls( $data['pexels_attachments'] );
		if ( ! empty( $data['pexels_attachments'] ) ) {
			$this->log_event( 'Pexels afbeeldingen opgeslagen in de mediabibliotheek.' );
		}
		$pages  = array_filter( array_map( 'trim', explode( ',', $data['pages'] ) ) );
		if ( empty( $pages ) ) {
			$pages = array( 'Home', 'Over ons', 'Diensten', 'Contact' );
		}
		$prompt = $this->build_page_prompt( $data );

		$created_pages = array();
		$created_page_ids = array();

		foreach ( $pages as $page_title ) {
			$page_prompt = $prompt . "\n\nGenereer content voor de pagina met de titel '{$page_title}' als WPBakery shortcodes.";
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
				$created_page_ids[] = $page_id;
				$this->log_event( sprintf( 'Pagina "%s" aangemaakt.', $page_title ) );
			}
		}

		$this->create_theme( $data );
		$this->log_event( 'Nieuw thema aangemaakt en geactiveerd.' );
		$this->store_generated_assets( $created_page_ids, $data );

		wp_send_json_success(
			array(
				'message' => 'Website succesvol aangemaakt.',
				'pages' => $created_pages,
				'logs' => $this->get_logs(),
			)
		);
	}

	public function handle_suggestions() {
		check_ajax_referer( 'wp_ai_builder_nonce', 'nonce' );

		$settings = $this->get_settings();
		$api_key  = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
		$model    = isset( $settings['model'] ) ? $settings['model'] : 'gpt-4o-mini';

		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => 'Vul eerst een OpenAI API key in bij Instellingen.' ) );
		}

		$data = $this->sanitize_brief( $_POST );

		$prompt = sprintf(
			"Je bent een ervaren UX/branding expert. Geef suggesties voor een WordPress website in het Nederlands. Branche: %s. Subbranche: %s. Website type: %s. Geef antwoord als geldig JSON met keys: siteType, pages, colors, notes. Geef colors als array met hexwaarden. Geef pages als komma-gescheiden string.",
			$data['sector'],
			$data['subsector'],
			$data['site_type']
		);

		$this->log_event( 'AI suggesties worden opgehaald.' );
		$result = WP_AI_Builder_OpenAI::request( $prompt, $api_key, $model );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$parsed = json_decode( trim( $result ), true );

		if ( empty( $parsed ) || ! is_array( $parsed ) ) {
			wp_send_json_error( array( 'message' => 'Kon de suggesties niet verwerken. Probeer opnieuw.' ) );
		}

		$this->log_event( 'AI suggesties succesvol toegevoegd.' );
		$parsed['logs'] = $this->get_logs();

		wp_send_json_success( $parsed );
	}

	public function handle_prompt_builder() {
		check_ajax_referer( 'wp_ai_builder_nonce', 'nonce' );

		$settings = $this->get_settings();
		$api_key  = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
		$model    = isset( $settings['model'] ) ? $settings['model'] : 'gpt-4o-mini';

		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => 'Vul eerst een OpenAI API key in bij Instellingen.' ) );
		}

		$data = $this->sanitize_brief( $_POST );

		$prompt = sprintf(
			"Schrijf een uitgebreide briefing in het Nederlands voor een WordPress website. Branche: %s. Subbranche: %s. Website type: %s. Pagina's: %s. Kernnotities: %s. Output alleen de briefingstekst zonder markdown.",
			$data['sector'],
			$data['subsector'],
			$data['site_type'],
			$data['pages'],
			$data['notes']
		);

		$this->log_event( 'AI prompt wordt gegenereerd.' );
		$result = WP_AI_Builder_OpenAI::request( $prompt, $api_key, $model );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$this->log_event( 'AI prompt succesvol gegenereerd.' );
		wp_send_json_success(
			array(
				'prompt' => sanitize_textarea_field( $result ),
				'logs' => $this->get_logs(),
			)
		);
	}

	public function handle_logs() {
		check_ajax_referer( 'wp_ai_builder_nonce', 'nonce' );

		wp_send_json_success( array( 'logs' => $this->get_logs() ) );
	}

	public function handle_cleanup() {
		check_ajax_referer( 'wp_ai_builder_nonce', 'nonce' );

		$generated = $this->get_generated_assets();
		$deleted_pages = 0;
		$deleted_media = 0;

		if ( ! empty( $generated['pages'] ) ) {
			foreach ( $generated['pages'] as $page_id ) {
				if ( wp_delete_post( (int) $page_id, true ) ) {
					$deleted_pages++;
				}
			}
		}

		if ( ! empty( $generated['media'] ) ) {
			foreach ( $generated['media'] as $attachment_id ) {
				if ( wp_delete_attachment( (int) $attachment_id, true ) ) {
					$deleted_media++;
				}
			}
		}

		if ( ! empty( $generated['theme'] ) ) {
			$theme_dir = trailingslashit( WP_CONTENT_DIR ) . 'themes/' . $generated['theme'];
			if ( is_dir( $theme_dir ) ) {
				$this->delete_directory( $theme_dir );
			}
		}

		delete_option( 'wp_ai_builder_generated' );
		$this->log_event( 'Alle gegenereerde content is verwijderd.' );

		wp_send_json_success(
			array(
				'message' => sprintf( 'Verwijderd: %d pagina(s) en %d media-bestand(en).', $deleted_pages, $deleted_media ),
				'logs' => $this->get_logs(),
			)
		);
	}

	private function sanitize_brief( $data ) {
		return array(
			'sector' => isset( $data['sector'] ) ? sanitize_text_field( $data['sector'] ) : '',
			'subsector' => isset( $data['subsector'] ) ? sanitize_text_field( $data['subsector'] ) : '',
			'logo_url' => isset( $data['logoUrl'] ) ? esc_url_raw( $data['logoUrl'] ) : '',
			'colors' => isset( $data['colors'] ) ? sanitize_text_field( $data['colors'] ) : '',
			'site_type' => isset( $data['siteType'] ) ? sanitize_text_field( $data['siteType'] ) : '',
			'pages' => isset( $data['pages'] ) ? sanitize_text_field( $data['pages'] ) : '',
			'notes' => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '',
			'prompt_mode' => isset( $data['promptMode'] ) ? sanitize_text_field( $data['promptMode'] ) : 'auto',
			'custom_prompt' => isset( $data['customPrompt'] ) ? sanitize_textarea_field( $data['customPrompt'] ) : '',
		);
	}

	private function build_preview_prompt( $data ) {
		$image_block = $this->format_images_for_prompt( $data );
		$extended_prompt = $this->get_extended_prompt( $data );

		return sprintf(
			"Maak een premium homepage preview in HTML (zonder markdown) met inline styles. Alles in het Nederlands. Branche: %s. Subbranche: %s. Website type: %s. Merk kleuren: %s. Logo URL: %s. Extra instructies: %s. %s %s Gebruik een hero met beeld, USP-sectie, dienstenblokken, testimonial en CTA. Voeg realistische, professionele copy toe.",
			$data['sector'],
			$data['subsector'],
			$data['site_type'],
			$data['colors'],
			$data['logo'],
			$data['notes'],
			$image_block,
			$extended_prompt
		);
	}

	private function build_page_prompt( $data ) {
		$image_block = $this->format_images_for_prompt( $data );
		$attachment_block = $this->format_attachments_for_prompt( $data );
		$extended_prompt = $this->get_extended_prompt( $data );

		return sprintf(
			"Je bouwt een volledige WordPress site in het Nederlands. Branche: %s. Subbranche: %s. Website type: %s. Merk kleuren: %s. Logo URL: %s. Extra instructies: %s. %s %s %s Geef de content terug als WPBakery shortcodes (vc_row, vc_column, vc_column_text, vc_single_image, vc_btn). Gebruik geen Gutenberg blocks en geen los HTML. Gebruik voor afbeeldingen de attachment ID's in vc_single_image.",
			$data['sector'],
			$data['subsector'],
			$data['site_type'],
			$data['colors'],
			$data['logo'],
			$data['notes'],
			$image_block,
			$attachment_block,
			$extended_prompt
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
		file_put_contents( $theme_dir . '/functions.php', "<?php\nadd_action( 'wp_enqueue_scripts', function() {\n\twp_enqueue_style( 'ai-builder-theme', get_stylesheet_uri(), array(), '0.1.0' );\n} );\n\nadd_action( 'after_setup_theme', function() {\n\tadd_theme_support( 'title-tag' );\n\tadd_theme_support( 'post-thumbnails' );\n\tadd_theme_support( 'custom-logo', array( 'height' => 120, 'width' => 240, 'flex-height' => true, 'flex-width' => true ) );\n} );\n\nadd_filter( 'use_block_editor_for_post_type', function( \$use_block_editor, \$post_type ) {\n\tif ( 'page' === \$post_type ) {\n\t\treturn false;\n\t}\n\treturn \$use_block_editor;\n}, 10, 2 );\n\nadd_filter( 'use_widgets_block_editor', '__return_false' );\n\nif ( function_exists( 'vc_set_as_theme' ) ) {\n\tvc_set_as_theme();\n}\n" );
		file_put_contents( $theme_dir . '/header.php', $header_php );
		file_put_contents( $theme_dir . '/footer.php', $footer_php );
		file_put_contents( $theme_dir . '/index.php', $index_php );
		file_put_contents( $theme_dir . '/page.php', $index_php );

		switch_theme( $theme_slug );

		if ( ! empty( $data['logo_id'] ) ) {
			set_theme_mod( 'custom_logo', (int) $data['logo_id'] );
		}
	}

	private function sideload_pexels_images( $images ) {
		if ( empty( $images ) ) {
			return array();
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_ids = array();
		foreach ( $images as $image_url ) {
			$attachment_id = media_sideload_image( $image_url, 0, null, 'id' );
			if ( ! is_wp_error( $attachment_id ) ) {
				$attachment_ids[] = (int) $attachment_id;
			}
		}

		return $attachment_ids;
	}

	private function store_generated_assets( $page_ids, $data ) {
		$existing = $this->get_generated_assets();
		$media = array_merge( $existing['media'], $data['pexels_attachments'] );
		if ( ! empty( $data['logo_id'] ) ) {
			$media[] = (int) $data['logo_id'];
		}

		update_option(
			'wp_ai_builder_generated',
			array(
				'pages' => array_unique( array_merge( $existing['pages'], $page_ids ) ),
				'media' => array_unique( $media ),
				'theme' => 'ai-builder-theme',
			)
		);
	}

	private function get_generated_assets() {
		$generated = get_option( 'wp_ai_builder_generated', array() );

		return array(
			'pages' => isset( $generated['pages'] ) ? (array) $generated['pages'] : array(),
			'media' => isset( $generated['media'] ) ? (array) $generated['media'] : array(),
			'theme' => isset( $generated['theme'] ) ? $generated['theme'] : '',
		);
	}

	private function log_event( $message ) {
		$logs = get_option( 'wp_ai_builder_log', array() );
		$logs[] = array(
			'time' => current_time( 'mysql' ),
			'message' => sanitize_text_field( $message ),
		);
		update_option( 'wp_ai_builder_log', $logs );
	}

	private function get_logs() {
		return get_option( 'wp_ai_builder_log', array() );
	}

	private function delete_directory( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$items = scandir( $dir );
		if ( ! $items ) {
			return;
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . '/' . $item;
			if ( is_dir( $path ) ) {
				$this->delete_directory( $path );
			} else {
				unlink( $path );
			}
		}

		rmdir( $dir );
	}

	private function attach_logo_data( $data, $allow_sideload ) {
		$logo_id = 0;
		$logo_url = $data['logo_url'];

		if ( ! empty( $_FILES['logoFile']['name'] ) ) {
			$logo_id = $this->handle_logo_upload();
			if ( $logo_id ) {
				$logo_url = wp_get_attachment_url( $logo_id );
			}
		} elseif ( $allow_sideload && ! empty( $logo_url ) ) {
			$logo_id = $this->sideload_logo( $logo_url );
		}

		$data['logo'] = $logo_url;
		$data['logo_id'] = $logo_id;

		return $data;
	}

	private function handle_logo_upload() {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_handle_upload( 'logoFile', 0 );

		if ( is_wp_error( $attachment_id ) ) {
			return 0;
		}

		return (int) $attachment_id;
	}

	private function sideload_logo( $url ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_sideload_image( $url, 0, null, 'id' );

		if ( is_wp_error( $attachment_id ) ) {
			return 0;
		}

		return (int) $attachment_id;
	}

	private function get_pexels_images( $data, $settings ) {
		if ( empty( $settings['pexels_api_key'] ) ) {
			return array();
		}

		$query = trim( $data['sector'] . ' ' . $data['subsector'] . ' ' . $data['site_type'] );
		if ( empty( $query ) ) {
			$query = 'business website';
		}

		$response = wp_remote_get(
			add_query_arg(
				array(
					'query' => $query,
					'per_page' => 6,
					'orientation' => 'landscape',
				),
				'https://api.pexels.com/v1/search'
			),
			array(
				'headers' => array(
					'Authorization' => $settings['pexels_api_key'],
				),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['photos'] ) ) {
			return array();
		}

		$images = array();
		foreach ( $body['photos'] as $photo ) {
			if ( ! empty( $photo['src']['large'] ) ) {
				$images[] = esc_url_raw( $photo['src']['large'] );
			}
		}

		return $images;
	}

	private function format_images_for_prompt( $data ) {
		if ( empty( $data['pexels_images'] ) ) {
			return 'Gebruik hoogwaardige stockbeelden waar passend.';
		}

		$lines = array_map(
			function( $url ) {
				return '- ' . $url;
			},
			$data['pexels_images']
		);

		return "Gebruik uitsluitend deze Pexels afbeeldingen:\n" . implode( "\n", $lines );
	}

	private function format_attachments_for_prompt( $data ) {
		if ( empty( $data['pexels_attachment_map'] ) ) {
			return 'Geen lokale attachments beschikbaar voor afbeeldingen.';
		}

		$lines = array();
		foreach ( $data['pexels_attachment_map'] as $attachment_id => $url ) {
			$lines[] = sprintf( '- ID %d: %s', $attachment_id, $url );
		}

		return "Gebruik deze mediabibliotheek attachments voor afbeeldingen:\n" . implode( "\n", $lines );
	}

	private function map_attachment_urls( $attachment_ids ) {
		$map = array();
		foreach ( $attachment_ids as $attachment_id ) {
			$url = wp_get_attachment_url( $attachment_id );
			if ( $url ) {
				$map[ (int) $attachment_id ] = esc_url_raw( $url );
			}
		}

		return $map;
	}

	private function get_extended_prompt( $data ) {
		if ( 'custom' === $data['prompt_mode'] && ! empty( $data['custom_prompt'] ) ) {
			return 'Gebruik deze aanvullende briefing: ' . $data['custom_prompt'];
		}

		return '';
	}

	private function get_settings() {
		return get_option(
			$this->option_key,
			array(
				'api_key' => '',
				'model' => 'gpt-4o-mini',
				'pexels_api_key' => '',
			)
		);
	}
}
}
