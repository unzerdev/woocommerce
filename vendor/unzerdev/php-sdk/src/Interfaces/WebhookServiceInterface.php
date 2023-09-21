<?php
/**
 * The interface for the WebhookService.
 *
 * Copyright (C) 2020 - today Unzer E-Com GmbH
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @link  https://docs.unzer.com/
 *
 * @package  UnzerSDK\Interfaces
 */

namespace UnzerSDK\Interfaces;

use UnzerSDK\Exceptions\UnzerApiException;
use UnzerSDK\Resources\AbstractUnzerResource;
use UnzerSDK\Resources\Webhook;
use RuntimeException;

interface WebhookServiceInterface
{
    /**
     * Creates Webhook resource
     *
     * @param string $url   The url the registered webhook event should be send to.
     * @param string $event The event to be registered.
     *
     * @return Webhook The newly created webhook resource.
     *
     * @throws UnzerApiException An UnzerApiException is thrown if there is an error returned on API-request.
     * @throws RuntimeException  A RuntimeException is thrown when there is an error while using the SDK.
     */
    public function createWebhook(string $url, string $event): Webhook;

    /**
     * Updates the given local Webhook object using the API.
     * Retrieves a Webhook resource, if the webhook parameter is the webhook id.
     *
     * @param Webhook|string $webhook
     *
     * @return Webhook The fetched webhook object.
     *
     * @throws UnzerApiException An UnzerApiException is thrown if there is an error returned on API-request.
     * @throws RuntimeException  A RuntimeException is thrown when there is an error while using the SDK.
     */
    public function fetchWebhook($webhook): Webhook;

    /**
     * Updates the Webhook resource of the api with the given object.
     *
     * @param Webhook $webhook
     *
     * @return Webhook The webhook object returned after update.
     *
     * @throws UnzerApiException An UnzerApiException is thrown if there is an error returned on API-request.
     * @throws RuntimeException  A RuntimeException is thrown when there is an error while using the SDK.
     */
    public function updateWebhook(Webhook $webhook): Webhook;

    /**
     * Deletes the given Webhook resource.
     *
     * @param Webhook|string $webhook The webhook object or the id of the webhook to be deleted.
     *
     * @return Webhook|AbstractUnzerResource|null Null if delete succeeded or the webhook object if not.
     *
     * @throws UnzerApiException An UnzerApiException is thrown if there is an error returned on API-request.
     * @throws RuntimeException  A RuntimeException is thrown when there is an error while using the SDK.
     */
    public function deleteWebhook($webhook);

    /**
     * Retrieves all registered webhooks and returns them in an array.
     *
     * @return array An array containing all registered webhooks.
     *
     * @throws UnzerApiException An UnzerApiException is thrown if there is an error returned on API-request.
     * @throws RuntimeException  A RuntimeException is thrown when there is an error while using the SDK.
     */
    public function fetchAllWebhooks(): array;

    /**
     * Deletes all registered webhooks.
     *
     * @throws UnzerApiException An UnzerApiException is thrown if there is an error returned on API-request.
     * @throws RuntimeException  A RuntimeException is thrown when there is an error while using the SDK.
     */
    public function deleteAllWebhooks();

    /**
     * Registers multiple Webhook events at once.
     *
     * @param string $url    The url the registered webhook events should be send to.
     * @param array  $events The events to be registered.
     *
     * @return array
     *
     * @throws UnzerApiException An UnzerApiException is thrown if there is an error returned on API-request.
     * @throws RuntimeException  A RuntimeException is thrown when there is an error while using the SDK.
     */
    public function registerMultipleWebhooks(string $url, array $events): array;

    /**
     * Fetches the resource corresponding to the given eventData.
     *
     * @param string|null $eventJson
     *
     * @return AbstractUnzerResource
     *
     * @throws UnzerApiException An UnzerApiException is thrown if there is an error returned on API-request.
     * @throws RuntimeException  A RuntimeException is thrown when there is an error while using the SDK.
     */
    public function fetchResourceFromEvent(string $eventJson = null): AbstractUnzerResource;
}
