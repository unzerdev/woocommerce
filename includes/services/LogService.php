<?php

namespace UnzerPayments\Services;

class LogService {
	const SOURCE = 'unzer';

	/**
	 * @var \WC_Logger|null
	 */
	private static $logger;

	public function log( $message, $level = 'debug', $data = null ) {
		if ( ! class_exists( '\WC_Logger' ) ) {
			return;
		}
		if ( self::$logger === null ) {
			self::$logger = new \WC_Logger( null, 'debug' );
		}
		self::$logger->log( $level, $message . ' | ' . $this->serializeData( $data ), array( 'source' => self::SOURCE ) );
	}

	public function debug( $message, $data = null ) {
		$this->log( $message, 'debug', $data );
	}

	public function warning( $message, $data = null ) {
		$this->log( $message, 'warning', $data );
	}

	public function error( $message, $data = null ) {
		$this->log( $message, 'error', $data );
	}

	private function serializeData( $data ): string {
		try {
			return serialize( $data );
		} catch ( \Throwable $e ) {
			$parts = array();
			if ( is_array( $data ) ) {
				foreach ( $data as $key => $value ) {
					try {
						$parts[ $key ] = serialize( $value );
					} catch ( \Throwable $e ) {
						$parts[ $key ] = gettype( $value );
					}
				}
			} else {
				$parts[] = gettype( $data );
			}
			return serialize( $parts );
		}
	}
}
