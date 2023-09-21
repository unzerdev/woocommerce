<?php
/**
 * This represents the Webhook resource.
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
 * @package  UnzerSDK\Resources
 */

namespace UnzerSDK\Resources;

use UnzerSDK\Adapter\HttpAdapterInterface;

class Webhook extends AbstractUnzerResource
{
    /** @var string $url */
    protected $url;

    /** @var string $event */
    protected $event;

    /**
     * Webhook constructor.
     *
     * @param string $url
     * @param string $event
     */
    public function __construct(string $url = '', string $event = '')
    {
        $this->url = $url;
        $this->event = $event;
    }

    /**
     * @return string|null
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * @param string $url
     *
     * @return Webhook
     */
    public function setUrl(string $url): Webhook
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getEvent(): ?string
    {
        return $this->event;
    }

    /**
     * @param string $event
     *
     * @return Webhook
     */
    public function setEvent(string $event): Webhook
    {
        $this->event = $event;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    protected function getResourcePath(string $httpMethod = HttpAdapterInterface::REQUEST_GET): string
    {
        return 'webhooks';
    }
}
