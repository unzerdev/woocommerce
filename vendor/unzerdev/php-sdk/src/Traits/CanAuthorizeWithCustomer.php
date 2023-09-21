<?php
/**
 * This trait makes a payment type authorizable with mandatory customer.
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
 * @package  UnzerSDK\Traits
 */

namespace UnzerSDK\Traits;

use UnzerSDK\Exceptions\UnzerApiException;
use UnzerSDK\Interfaces\UnzerParentInterface;
use UnzerSDK\Resources\Basket;
use UnzerSDK\Resources\Customer;
use UnzerSDK\Resources\Metadata;
use UnzerSDK\Resources\TransactionTypes\Authorization;
use RuntimeException;

trait CanAuthorizeWithCustomer
{
    /**
     * Authorize an amount with the given currency.
     * Throws UnzerApiException if the transaction could not be performed (e.g. increased risk etc.).
     *
     * @param                 $amount
     * @param                 $currency
     * @param                 $returnUrl
     * @param Customer|string $customer
     * @param string|null     $orderId
     * @param Metadata|null   $metadata
     * @param Basket|null     $basket           The Basket object corresponding to the payment.
     *                                          The Basket object will be created automatically if it does not exist
     *                                          yet (i.e. has no id).
     * @param string|null     $invoiceId        The external id of the invoice.
     * @param string|null     $paymentReference A reference text for the payment.
     * @param string|null     $recurrenceType   Recurrence type used for recurring payment.
     *                                          See \UnzerSDK\Constants\RecurrenceTypes to find all supported types.
     *
     * @return Authorization
     *
     * @throws UnzerApiException An UnzerApiException is thrown if there is an error returned on API-request.
     * @throws RuntimeException  A RuntimeException is thrown when there is an error while using the SDK.
     */
    public function authorize(
        $amount,
        $currency,
        $returnUrl,
        $customer,
        $orderId = null,
        $metadata = null,
        $basket = null,
        $invoiceId = null,
        $paymentReference = null,
        $recurrenceType = null
    ): Authorization {
        if ($this instanceof UnzerParentInterface) {
            return $this->getUnzerObject()->authorize(
                $amount,
                $currency,
                $this,
                $returnUrl,
                $customer,
                $orderId,
                $metadata,
                $basket,
                $invoiceId,
                $paymentReference,
                $recurrenceType
            );
        }

        throw new RuntimeException(
            self::class . ' must implement UnzerParentInterface to enable ' . __METHOD__ . ' transaction.'
        );
    }
}
