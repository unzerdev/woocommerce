<?php

namespace UnzerPayments\Services;

use UnzerPayments\Controllers\WebhookController;
use UnzerPayments\Gateways\Invoice;
use UnzerSDK\Constants\WebhookEvents;
use UnzerSDK\Exceptions\UnzerApiException;
use UnzerSDK\Resources\Webhook;
use UnzerSDK\Unzer;

class WebhookManagementService {

	/**
	 * @var \UnzerSDK\Unzer
	 */
	private $unzerManager;

	public function __construct( $slug = null ) {
		$paymentService = new PaymentService();
		if ( ! empty( $slug ) ) {
			$invoiceGateway = new Invoice();
			$privateKey     = $invoiceGateway->get_option( 'private_key_' . $slug );
			if ( empty( $privateKey ) ) {
				throw new \Exception( 'Private key not found' );
			}
			$this->unzerManager = new Unzer( $privateKey );
		} else {
			$this->unzerManager = $paymentService->getUnzerManager( null );
		}
	}

	public function fetchAllWebhooks() {
		$returnData = array();
		/** @var Webhook $webhook */
		foreach ( $this->unzerManager->fetchAllWebhooks() as $webhook ) {
			$returnData[] = $webhook->expose();
		}
		return $returnData;
	}

	public function isWebhookRegistered() {
		$currentUrl = self::getWebhookUrl();
		/** @var Webhook $webhook */
		foreach ( $this->unzerManager->fetchAllWebhooks() as $webhook ) {
			if ( $webhook->getUrl() === $currentUrl ) {
				return true;
			}
		}
		return false;
	}

	public function deleteWebhook( $webhookId ) {
		$this->unzerManager->deleteWebhook( $webhookId );
	}

	/**
	 * @throws UnzerApiException
	 */
	public function addCurrentWebhook() {
		$this->unzerManager->createWebhook( self::getWebhookUrl(), WebhookEvents::ALL );
	}

	public static function getWebhookUrl(): string {
		return str_replace( 'http://', 'https://', WC()->api_request_url( WebhookController::WEBHOOK_ROUTE_SLUG ) ); // TODO only testing
	}
}
