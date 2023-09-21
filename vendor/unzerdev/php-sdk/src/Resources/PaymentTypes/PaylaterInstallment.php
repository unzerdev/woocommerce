<?php
/**
 * This represents the Paylater Installment payment type.
 *
 * Copyright (C) 2023 - today Unzer E-Com GmbH
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
 * @package  UnzerSDK\PaymentTypes
 */

namespace UnzerSDK\Resources\PaymentTypes;

class PaylaterInstallment extends BasePaymentType
{
    protected const SUPPORT_DIRECT_PAYMENT_CANCEL = true;

    /** @var string $inquiryId */
    protected $inquiryId;

    /** @var int */
    protected $numberOfRates;

    /** @var string $iban */
    protected $iban;

    /** @var string $country */
    protected $country;

    /** @var string $holder */
    protected $holder;

    /**
     * @param string|null $inquiryId
     * @param int|null    $numberOfRates
     * @param string|null $iban
     * @param string|null $country
     * @param string|null $accountHolder
     */
    public function __construct($inquiryId = null, $numberOfRates = null, $iban = null, $country = null, $accountHolder = null)
    {
        $this->inquiryId = $inquiryId;
        $this->numberOfRates = $numberOfRates;
        $this->iban = $iban;
        $this->country = $country;
        $this->holder = $accountHolder;
    }

    /**
     * @return string|null
     */
    public function getInquiryId(): ?string
    {
        return $this->inquiryId;
    }

    /**
     * @param string|null $inquiryId
     *
     * @return PaylaterInstallment
     */
    public function setInquiryId(?string $inquiryId): PaylaterInstallment
    {
        $this->inquiryId = $inquiryId;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getNumberOfRates(): ?int
    {
        return $this->numberOfRates;
    }

    /**
     * @param int|null $numberOfRates
     *
     * @return PaylaterInstallment
     */
    public function setNumberOfRates(?int $numberOfRates): PaylaterInstallment
    {
        $this->numberOfRates = $numberOfRates;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getIban(): ?string
    {
        return $this->iban;
    }

    /**
     * @param string|null $iban
     *
     * @return $this
     */
    public function setIban(?string $iban): self
    {
        $this->iban = $iban;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getCountry(): ?string
    {
        return $this->country;
    }

    /**
     * @param string|null $country
     *
     * @return $this
     */
    public function setCountry(?string $country): self
    {
        $this->country = $country;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getHolder(): ?string
    {
        return $this->holder;
    }

    /**
     * @param string|null $holder
     *
     * @return $this
     */
    public function setHolder(?string $holder): self
    {
        $this->holder = $holder;
        return $this;
    }
}
