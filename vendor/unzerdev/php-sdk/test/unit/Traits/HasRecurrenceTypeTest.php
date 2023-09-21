<?php

/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpDocMissingThrowsInspection */
/**
 * This class defines unit tests to verify functionality of the HasRecurrenceType trait.
 *
 * Copyright (C) 2021 - today Unzer E-Com GmbH
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
 * @package  UnzerSDK\test\unit
 */

namespace UnzerSDK\test\unit\Traits;

use UnzerSDK\Constants\RecurrenceTypes;
use UnzerSDK\Resources\Payment;
use UnzerSDK\Resources\PaymentTypes\Card;
use UnzerSDK\Resources\Recurring;
use UnzerSDK\Resources\TransactionTypes\Charge;
use UnzerSDK\test\BasePaymentTest;
use UnzerSDK\Unzer;

class HasRecurrenceTypeTest extends BasePaymentTest
{
    /**
     * Verify getters setters.
     *
     * @test
     */
    public function hasRecurrenceTypeGettersAndSettersShouldWorkProperly(): void
    {
        $unzerObj = new Unzer('s-priv-123345');
        $paymentType = (new Card(null, null))->setId('123');
        $payment = new Payment($unzerObj);
        $payment->setPaymentType($paymentType);
        $charge = new Charge(123.4, 'myCurrency', 'https://my-return-url.test');
        $charge->setPayment($payment);

        // Properties are not set initially.
        $this->assertNull($charge->getRecurrenceType());
        $this->assertNull($charge->getAdditionalTransactionData());

        $charge->setRecurrenceType(RecurrenceTypes::ONE_CLICK);

        // Check correct data structure.
        $this->assertNotNull($charge->getAdditionalTransactionData());
        $this->assertTrue(property_exists($charge->getAdditionalTransactionData(), 'card'));
        $this->assertTrue(property_exists($charge->getAdditionalTransactionData()->card, 'recurrenceType'));

        // Recurrence type can be updated correctly.
        $this->assertEquals('oneclick', $charge->getRecurrenceType());
        $charge->setRecurrenceType(RecurrenceTypes::SCHEDULED);
        $this->assertEquals('scheduled', $charge->getRecurrenceType());
        $charge->setRecurrenceType(RecurrenceTypes::UNSCHEDULED);
        $this->assertEquals('unscheduled', $charge->getRecurrenceType());
    }

    /**
     * Recurrence type defined in trade should be exposed properly.
     *
     * @test
     */
    public function recurrenceTypeShouldBeExposedProperly(): void
    {
        $unzerObj    = new Unzer('s-priv-123345');
        $paymentType     = (new Card(null, null))->setId('123');
        $payment         = new Payment();
        $payment->setParentResource($unzerObj)->setPaymentType($paymentType);

        $charge          = (new Charge())->setPayment($payment);

        $this->assertEmpty($charge->getAdditionalTransactionData());
        $this->assertEmpty($charge->getRecurrenceType());

        $charge->setRecurrenceType(RecurrenceTypes::ONE_CLICK);
        $exposedTransaction = $charge->expose();
        $this->assertEquals('oneclick', $exposedTransaction['additionalTransactionData']->card['recurrenceType']);
        $this->assertStringContainsString(
            '"additionalTransactionData":{"card":{"recurrenceType":"oneclick"}}',
            $charge->jsonSerialize()
        );
    }

    /**
     * Recurrence type defined in trade should be exposed properly.
     *
     * @test
     */
    public function responseShouldBeHandledProperlyWithRecurrenceType(): void
    {
        $unzerObj    = new Unzer('s-priv-123345');
        $paymentType     = (new Card(null, null))->setId('123');
        $payment         = new Payment();
        $payment->setParentResource($unzerObj)->setPaymentType($paymentType);

        $charge          = (new Charge())->setPayment($payment);

        $this->assertEmpty($charge->getAdditionalTransactionData());
        $this->assertEmpty($charge->getRecurrenceType());

        $testResponse = (object)[
            'additionalTransactionData' => (object) [
                'card' => (object)['recurrenceType' => 'oneclick']
            ]
        ];

        $charge->handleResponse($testResponse);

        $this->assertEquals('oneclick', $charge->getRecurrenceType());
        $this->assertStringContainsString(
            '"additionalTransactionData":{"card":{"recurrenceType":"oneclick"}}',
            $charge->jsonSerialize()
        );
    }

    /**
     * recurrence type should be set properly for recurring.
     *
     * @test
     */
    public function recurrenceTypeShouldBeSetProperlyForRecurring()
    {
        $paymentType = $this->createCardObject();
        $recurring = new Recurring('typeId', 'returnUrl');
        $recurring->setRecurrenceType(RecurrenceTypes::SCHEDULED, $paymentType);

        $this->assertEquals('scheduled', $recurring->getRecurrenceType());
    }
}
