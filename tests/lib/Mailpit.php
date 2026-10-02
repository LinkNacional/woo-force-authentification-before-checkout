<?php

namespace WcfaTests\Lib;

/**
 * Tiny client for the LocalWP Mailpit catcher.
 *
 * LocalWP routes all outgoing mail to Mailpit; the HTTP API lets the suite read
 * what the plugin actually sent. Base URL comes from WC_FA_MAILPIT_URL.
 */
class Mailpit {

	/**
	 * @return string Mailpit HTTP base URL (no trailing slash).
	 */
	public static function baseUrl(): string {
		$url = getenv( 'WC_FA_MAILPIT_URL' );

		if ( ! $url ) {
			$url = 'http://127.0.0.1:10000';
		}

		return rtrim( $url, '/' );
	}

	/**
	 * Total number of captured messages.
	 *
	 * @return int
	 */
	public static function count(): int {
		$data = self::get( '/api/v1/messages?limit=1' );

		return isset( $data['messages_count'] ) ? (int) $data['messages_count'] : 0;
	}

	/**
	 * Recent messages (metadata only), newest first.
	 *
	 * @param int $limit Max messages.
	 * @return array<int,array>
	 */
	public static function messages( int $limit = 60 ): array {
		$data = self::get( '/api/v1/messages?limit=' . $limit );

		return isset( $data['messages'] ) && is_array( $data['messages'] ) ? $data['messages'] : array();
	}

	/**
	 * Fetch a full message (body included) by ID.
	 *
	 * @param string $id Message ID.
	 * @return array|null
	 */
	public static function message( string $id ): ?array {
		$data = self::get( '/api/v1/message/' . rawurlencode( $id ) );

		return is_array( $data ) ? $data : null;
	}

	/**
	 * Newest message whose Subject contains $needle.
	 *
	 * @param string $needle Subject fragment.
	 * @return array|null
	 */
	public static function find( string $needle ): ?array {
		foreach ( self::messages() as $meta ) {
			if ( isset( $meta['Subject'], $meta['ID'] ) && false !== stripos( $meta['Subject'], $needle ) ) {
				return self::message( $meta['ID'] );
			}
		}

		return null;
	}

	/**
	 * Full message body as plain text (falls back to stripped HTML).
	 *
	 * @param array $message Full message array.
	 * @return string
	 */
	public static function text( array $message ): string {
		if ( ! empty( $message['Text'] ) ) {
			return (string) $message['Text'];
		}

		if ( ! empty( $message['HTML'] ) ) {
			return trim( wp_strip_all_tags( (string) $message['HTML'] ) );
		}

		return '';
	}

	/**
	 * Find the OTP code inside a message: a standalone run of $length digits.
	 *
	 * @param array $message Full message array.
	 * @param int   $length  Expected number of digits.
	 * @return string|null
	 */
	public static function code( array $message, int $length ): ?string {
		$text = self::text( $message );

		if ( '' === $text ) {
			return null;
		}

		if ( preg_match( '/(?<!\d)(\d{' . $length . '})(?!\d)/', $text, $m ) ) {
			return $m[1];
		}

		return null;
	}

	/**
	 * Poll until $predicate returns true or the timeout elapses.
	 *
	 * @param callable $predicate Receives nothing, returns bool.
	 * @param int      $timeoutMs Max wait in milliseconds.
	 * @return bool
	 */
	public static function waitFor( callable $predicate, int $timeoutMs = 6000 ): bool {
		$deadline = microtime( true ) + ( $timeoutMs / 1000 );

		do {
			if ( $predicate() ) {
				return true;
			}

			usleep( 150000 );
		} while ( microtime( true ) < $deadline );

		return (bool) $predicate();
	}

	/**
	 * Perform a GET against the Mailpit API.
	 *
	 * @param string $path Path (starting with /).
	 * @return array
	 */
	private static function get( string $path ): array {
		$response = wp_remote_get(
			self::baseUrl() . $path,
			array( 'timeout' => 8 )
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		$json = json_decode( $body, true );

		return is_array( $json ) ? $json : array();
	}
}
