<?php
/**
 * RiskData class for Paylater payment types.
 *
 * Copyright (C) 2022 - today Unzer E-Com GmbH
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

use UnzerSDK\Constants\CustomerGroups;
use UnzerSDK\Resources\AbstractUnzerResource;

class RiskData extends AbstractUnzerResource
{
    /** @var string|null $threatMetrixId */
    protected $threatMetrixId;

    /** @var string|null $registrationLevel */
    protected $registrationLevel;

    /** @var string|null $registrationDate */
    protected $registrationDate;

    /** @var string|null $customerId */
    protected $customerId;

    /** @var string|null $customerGroup */
    protected $customerGroup;

    /** @var int|null $confirmedOrders */
    protected $confirmedOrders;

    /** @var float|null $confirmedAmount */
    protected $confirmedAmount;

    /**
     * @return string|null
     */
    public function getThreatMetrixId(): ?string
    {
        return $this->threatMetrixId;
    }

    /**
     * @param string|null $threatMetrixId
     *
     * @return RiskData
     */
    public function setThreatMetrixId(?string $threatMetrixId): RiskData
    {
        $this->threatMetrixId = $threatMetrixId;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getRegistrationLevel(): ?string
    {
        return $this->registrationLevel;
    }

    /**
     * @param string|null $registrationLevel
     *
     * @return RiskData
     */
    public function setRegistrationLevel(?string $registrationLevel): RiskData
    {
        $this->registrationLevel = $registrationLevel;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getRegistrationDate(): ?string
    {
        return $this->registrationDate;
    }

    /**
     * @param string|null $registrationDate Dateformat must be "YYYYMMDD".
     *
     * @return RiskData
     */
    public function setRegistrationDate(?string $registrationDate): RiskData
    {
        $this->registrationDate = $registrationDate;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    /**
     * @param string|null $customerId
     *
     * @return RiskData
     */
    public function setCustomerId(?string $customerId): RiskData
    {
        $this->customerId = $customerId;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getCustomerGroup(): ?string
    {
        return $this->customerGroup;
    }

    /**
     * @param string|null $customerGroup
     *
     * @see CustomerGroups
     *
     * @return RiskData
     */
    public function setCustomerGroup(?string $customerGroup): RiskData
    {
        $this->customerGroup = $customerGroup;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getConfirmedOrders(): ?int
    {
        return $this->confirmedOrders;
    }

    /**
     * @param int|null $confirmedOrders
     *
     * @return RiskData
     */
    public function setConfirmedOrders(?int $confirmedOrders): RiskData
    {
        $this->confirmedOrders = $confirmedOrders;
        return $this;
    }

    /**
     * @return float|null
     */
    public function getConfirmedAmount(): ?float
    {
        return $this->confirmedAmount;
    }

    /**
     * @param float|null $confirmedAmount
     *
     * @return RiskData
     */
    public function setConfirmedAmount(?float $confirmedAmount): RiskData
    {
        $this->confirmedAmount = $confirmedAmount;
        return $this;
    }
}
