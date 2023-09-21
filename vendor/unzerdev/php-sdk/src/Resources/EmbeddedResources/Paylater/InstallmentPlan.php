<?php

/**
 * Resource representing the installment plan for Paylater Installment.
 *
 * Copyright (C) 2023 - today Unzer E-Com GmbH
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 *
 *  @link  https://docs.unzer.com/
 *
 *  @package  UnzerSDK
 *
 */

namespace UnzerSDK\Resources\EmbeddedResources\Paylater;

use UnzerSDK\Adapter\HttpAdapterInterface;
use UnzerSDK\Resources\AbstractUnzerResource;
use stdClass;

class InstallmentPlan extends AbstractUnzerResource
{
    /** @var int $numberOfRates */
    private $numberOfRates;

    private $totalAmount;

    private $nominalInterestRate;

    /** @var float $effectiveInterestRate */
    private $effectiveInterestRate;

    /** @var string $secciUrl */
    private $secciUrl;

    /** @var InstallmentRate */
    private $installmentRates;

    /**
     * @return string
     */
    public function getSecciUrl(): string
    {
        return $this->secciUrl;
    }

    /**
     * @param string $secciUrl
     *
     * @return InstallmentPlan
     */
    public function setSecciUrl(string $secciUrl): InstallmentPlan
    {
        $this->secciUrl = $secciUrl;
        return $this;
    }

    /**
     * @return int
     */
    public function getNumberOfRates(): int
    {
        return $this->numberOfRates;
    }

    /**
     * @param int $numberOfRates
     *
     * @return InstallmentPlan
     */
    public function setNumberOfRates(int $numberOfRates): InstallmentPlan
    {
        $this->numberOfRates = $numberOfRates;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getTotalAmount()
    {
        return $this->totalAmount;
    }

    /**
     * @param mixed $totalAmount
     *
     * @return InstallmentPlan
     */
    public function setTotalAmount($totalAmount)
    {
        $this->totalAmount = $totalAmount;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getNominalInterestRate()
    {
        return $this->nominalInterestRate;
    }

    /**
     * @param mixed $nominalInterestRate
     *
     * @return InstallmentPlan
     */
    public function setNominalInterestRate($nominalInterestRate)
    {
        $this->nominalInterestRate = $nominalInterestRate;
        return $this;
    }

    /**
     * @return float
     */
    public function getEffectiveInterestRate(): float
    {
        return $this->effectiveInterestRate;
    }

    /**
     * @param float $effectiveInterestRate
     *
     * @return InstallmentPlan
     */
    public function setEffectiveInterestRate(float $effectiveInterestRate): InstallmentPlan
    {
        $this->effectiveInterestRate = $effectiveInterestRate;
        return $this;
    }

    /**
     * @return InstallmentRate[]|null
     */
    public function getInstallmentRates(): ?array
    {
        return $this->installmentRates;
    }

    /**
     * @param InstallmentRate[] $installmentRates
     *
     * @return InstallmentPlan
     */
    protected function setInstallmentRates(array $installmentRates): InstallmentPlan
    {
        $this->installmentRates = $installmentRates;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function handleResponse(stdClass $response, string $method = HttpAdapterInterface::REQUEST_GET): void
    {
        parent::handleResponse($response, $method);

        if (isset($response->installmentRates)) {
            $rates = [];
            foreach ($response->installmentRates as $rate) {
                $rates[] = new InstallmentRate($rate->date, $rate->rate);
            }
            $this->setInstallmentRates($rates);
        }
    }
}
