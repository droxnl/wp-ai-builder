<?php

if ( ! class_exists( 'WP_AI_Builder_OpenAI' ) ) {
class WP_AI_Builder_OpenAI {
	public static function request( $prompt, $api_key, $model ) {
		$body = array(
			'model' => $model,
			'messages' => array(
				array(
					'role' => 'system',
					'content' => 'Je bent een behulpzame assistent die professionele, beknopte teksten in het Nederlands levert voor WordPress pagina’s en op verzoek WPBakery shortcodes gebruikt.',
				),
				array(
					'role' => 'user',
					'content' => $prompt,
				),
			),
			'temperature' => 0.7,
		);

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body' => wp_json_encode( $body ),
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( null === $data ) {
			return new WP_Error( 'openai_error', 'OpenAI API response was not valid JSON.', array( 'status' => $status ) );
		}

		$content = '';

		if ( isset( $data['choices'][0]['message']['content'] ) ) {
			$content = $data['choices'][0]['message']['content'];
		} elseif ( isset( $data['choices'][0]['text'] ) ) {
			$content = $data['choices'][0]['text'];
		} elseif ( isset( $data['output'][0]['content'][0]['text'] ) ) {
			$content = $data['output'][0]['content'][0]['text'];
		}

		if ( is_array( $content ) && isset( $content[0]['text'] ) ) {
			$content = $content[0]['text'];
		}

		if ( 200 !== $status || '' === $content ) {
			$error_message = 'OpenAI API request failed.';

			if ( isset( $data['error']['message'] ) ) {
				$error_message = $data['error']['message'];
			}

			return new WP_Error( 'openai_error', $error_message, array( 'status' => $status, 'body' => $data ) );
		}

		return $content;
	}
}
}
