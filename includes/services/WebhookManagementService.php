<?php

namespace UnzerPayments\Services;

use UnzerPayments\Controllers\WebhookController;
use UnzerSDK\Constants\WebhookEvents;
use UnzerSDK\Exceptions\UnzerApiException;
use UnzerSDK\Resources\Webhook;

class WebhookManagementService
{
    /**
     * @var \UnzerSDK\Unzer
     */
    private $unzerManager;

    public function __construct()
    {
        $paymentService = new PaymentService();
        $this->unzerManager = $paymentService->getUnzerManager();
    }

    public function fetchAllWebhooks()
    {
        $returnData = [];
        /** @var Webhook $webhook */
        foreach ($this->unzerManager->fetchAllWebhooks() as $webhook) {
            $returnData[] = $webhook->expose();
        }
        return $returnData;
    }

    public function isWebhookRegistered()
    {
        $currentUrl = self::getWebhookUrl();
        /** @var Webhook $webhook */
        foreach ($this->unzerManager->fetchAllWebhooks() as $webhook) {
            if ($webhook->getUrl() === $currentUrl) {
                return true;
            }
        }
        return false;
    }

    public function deleteWebhook($webhookId)
    {
        $this->unzerManager->deleteWebhook($webhookId);
    }

    /**
     * @throws UnzerApiException
     */
    public function addCurrentWebhook()
    {
        $this->unzerManager->createWebhook(self::getWebhookUrl(), WebhookEvents::ALL);
    }

    public static function getWebhookUrl(): string
    {
        return str_replace('http://', 'https://', WC()->api_request_url(WebhookController::WEBHOOK_ROUTE_SLUG)); //TODO only testing
    }
}
