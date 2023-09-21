<?php
/**
 * Represents detailed information for Card payment types.
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
 * @package  UnzerSDK\Resources\EmbeddedResources
 */

namespace UnzerSDK\Resources\EmbeddedResources;

use UnzerSDK\Resources\AbstractUnzerResource;

class CardDetails extends AbstractUnzerResource
{
    /** @var string|null $cardType */
    protected $cardType;

    /** @var string|null $account */
    protected $account;

    /** @var string|null $account */
    protected $countryIsoA2;

    /** @var string|null $countryName */
    protected $countryName;

    /** @var string|null $issuerName */
    protected $issuerName;

    /** @var string|null $issuerUrl */
    protected $issuerUrl;

    /** @var string|null $issuerPhoneNumber */
    protected $issuerPhoneNumber;

    /**
     * @return string|null
     */
    public function getCardType(): ?string
    {
        return $this->cardType;
    }

    /**
     * @param string|null $cardType
     *
     * @return CardDetails
     */
    protected function setCardType(?string $cardType): CardDetails
    {
        $this->cardType = $cardType;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getAccount(): ?string
    {
        return $this->account;
    }

    /**
     * @param string|null $account
     *
     * @return CardDetails
     */
    protected function setAccount(?string $account): CardDetails
    {
        $this->account = $account;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getCountryIsoA2(): ?string
    {
        return $this->countryIsoA2;
    }

    /**
     * @param string|null $countryIsoA2
     *
     * @return CardDetails
     */
    protected function setCountryIsoA2(?string $countryIsoA2): CardDetails
    {
        $this->countryIsoA2 = $countryIsoA2;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getCountryName(): ?string
    {
        return $this->countryName;
    }

    /**
     * @param string|null $countryName
     *
     * @return CardDetails
     */
    protected function setCountryName(?string $countryName): CardDetails
    {
        $this->countryName = $countryName;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getIssuerName(): ?string
    {
        return $this->issuerName;
    }

    /**
     * @param string|null $issuerName
     *
     * @return CardDetails
     */
    protected function setIssuerName(?string $issuerName): CardDetails
    {
        $this->issuerName = $issuerName;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getIssuerUrl(): ?string
    {
        return $this->issuerUrl;
    }

    /**
     * @param string|null $issuerUrl
     *
     * @return CardDetails
     */
    protected function setIssuerUrl(?string $issuerUrl): CardDetails
    {
        $this->issuerUrl = $issuerUrl;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getIssuerPhoneNumber(): ?string
    {
        return $this->issuerPhoneNumber;
    }

    /**
     * @param string|null $issuerPhoneNumber
     *
     * @return CardDetails
     */
    protected function setIssuerPhoneNumber(?string $issuerPhoneNumber): CardDetails
    {
        $this->issuerPhoneNumber = $issuerPhoneNumber;
        return $this;
    }
}
