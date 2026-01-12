<?php

class WP_AI_Builder_OpenAI {
	public static function request( $prompt, $api_key, $model ) {
		$body = array(
			'model' => $model,
			'messages' => array(
				array(
					'role' => 'system',
					'content' => 'Je bent een behulpzame assistent die schone HTML en professionele, beknopte teksten in het Nederlands levert voor WordPress pagina’s.',
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

		if ( 200 !== $status || empty( $data['choices'][0]['message']['content'] ) ) {
			return new WP_Error( 'openai_error', 'OpenAI API request failed.', array( 'status' => $status, 'body' => $data ) );
		}

		return $data['choices'][0]['message']['content'];
	}
}
