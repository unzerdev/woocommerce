<?php

/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpDocMissingThrowsInspection */
/**
 * This class defines integration tests to verify interface and
 * functionality of the payment method sepa direct debit.
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
 * @package  UnzerSDK\test\integration\PaymentTypes
 */

namespace UnzerSDK\test\integration\PaymentTypes;

use UnzerSDK\Constants\ApiResponseCodes;
use UnzerSDK\Exceptions\UnzerApiException;
use UnzerSDK\Resources\PaymentTypes\SepaDirectDebit;
use UnzerSDK\test\BaseIntegrationTest;

class SepaDirectDebitTest extends BaseIntegrationTest
{
    protected function setUp(): void
    {
        $this->useLegacyKey();
    }

    /**
     * Verify sepa direct debit can be created.
     *
     * @test
     */
    public function sepaDirectDebitShouldBeCreatableWithMandatoryFieldsOnly(): void
    {
        $directDebit = new SepaDirectDebit('DE89370400440532013000');
        /** @var SepaDirectDebit $directDebit */
        $directDebit = $this->unzer->createPaymentType($directDebit);
        $this->assertInstanceOf(SepaDirectDebit::class, $directDebit);
        $this->assertNotNull($directDebit->getId());

        /** @var SepaDirectDebit $fetchedDirectDebit */
        $fetchedDirectDebit = $this->unzer->fetchPaymentType($directDebit->getId());
        $this->assertEquals($directDebit->expose(), $fetchedDirectDebit->expose());
    }

    /**
     * Verify sepa direct debit can be created.
     *
     * @test
     */
    public function sepaDirectDebitShouldBeCreatable(): void
    {
        $sdd = (new SepaDirectDebit('DE89370400440532013000'))->setHolder('Max Mustermann')->setBic('COBADEFFXXX');
        /** @var SepaDirectDebit $sdd */
        $sdd = $this->unzer->createPaymentType($sdd);
        $this->assertInstanceOf(SepaDirectDebit::class, $sdd);
        $this->assertNotNull($sdd->getId());

        /** @var SepaDirectDebit $fetchedDirectDebit */
        $fetchedDirectDebit = $this->unzer->fetchPaymentType($sdd->getId());
        $this->assertEquals($sdd->expose(), $fetchedDirectDebit->expose());
    }

    /**
     * Verify authorization is not allowed for sepa direct debit.
     *
     * @test
     */
    public function authorizeShouldThrowException(): void
    {
        $sdd = (new SepaDirectDebit('DE89370400440532013000'))->setHolder('Max Mustermann')->setBic('COBADEFFXXX');
        /** @var SepaDirectDebit $sdd */
        $sdd = $this->unzer->createPaymentType($sdd);
        $this->expectException(UnzerApiException::class);
        $this->expectExceptionCode(ApiResponseCodes::API_ERROR_TRANSACTION_AUTHORIZE_NOT_ALLOWED);

        $this->unzer->authorize(1.0, 'EUR', $sdd, self::RETURN_URL);
    }

    /**
     * @test
     */
    public function directDebitShouldBeChargeable(): void
    {
        $sdd = (new SepaDirectDebit('DE89370400440532013000'))->setHolder('Max Mustermann')->setBic('COBADEFFXXX');
        /** @var SepaDirectDebit $sdd */
        $sdd = $this->unzer->createPaymentType($sdd);
        $charge = $sdd->charge(100.0, 'EUR', self::RETURN_URL);
        $this->assertNotNull($charge);
        $this->assertNotNull($charge->getId());
    }

    /**
     * Verify sdd charge is refundable.
     *
     * @test
     */
    public function directDebitChargeShouldBeRefundable(): void
    {
        $sdd = (new SepaDirectDebit('DE89370400440532013000'))->setHolder('Max Mustermann')->setBic('COBADEFFXXX');
        /** @var SepaDirectDebit $sdd */
        $sdd = $this->unzer->createPaymentType($sdd);
        $charge = $sdd->charge(100.0, 'EUR', self::RETURN_URL);

        // when
        $cancellation = $charge->cancel();

        // then
        $this->assertNotNull($cancellation);
        $this->assertNotNull($cancellation->getId());
    }
}
