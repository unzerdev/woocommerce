<?php
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpDocMissingThrowsInspection */
/**
 * This class defines unit tests to verify functionality of the CanPayoutWithCustomer trait.
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
 * @package  UnzerSDK\test\unit
 */
namespace UnzerSDK\test\unit\Traits;

use UnzerSDK\Unzer;
use UnzerSDK\Resources\Customer;
use UnzerSDK\Resources\Metadata;
use UnzerSDK\Resources\TransactionTypes\Payout;
use UnzerSDK\test\BasePaymentTest;
use RuntimeException;

class CanPayoutWithCustomerTest extends BasePaymentTest
{
    /**
     * Verify payout method throws exception if the class does not implement the UnzerParentInterface.
     *
     * @test
     */
    public function payoutShouldThrowExceptionIfTheClassDoesNotImplementParentInterface(): void
    {
        $dummy = new TraitDummyWithCustomerWithoutParentIF();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TraitDummyWithCustomerWithoutParentIF');

        $dummy->payout(1.0, 'MyCurrency', 'https://return.url', new Customer());
    }

    /**
     * Verify payout method propagates payout method to Unzer object.
     *
     * @test
     */
    public function payoutShouldPropagatePayoutToUnzer(): void
    {
        $unzerMock = $this->getMockBuilder(Unzer::class)->setMethods(['payout'])->disableOriginalConstructor()->getMock();
        $dummyMock     = $this->getMockBuilder(TraitDummyWithCustomerWithParentIF::class)->setMethods(['getUnzerObject'])->getMock();

        $payout = new Payout();
        $customer  = (new Customer())->setId('123');
        $metadata  = new Metadata();
        $dummyMock->expects($this->exactly(3))->method('getUnzerObject')->willReturn($unzerMock);
        $unzerMock->expects($this->exactly(3))->method('payout')
            ->withConsecutive(
                [1.2, 'MyCurrency2', $dummyMock, 'https://return.url2', $customer, null],
                [1.3, 'MyCurrency3', $dummyMock, 'https://return.url3', $customer, 'orderId'],
                [1.4, 'MyCurrency3', $dummyMock, 'https://return.url3', $customer, 'orderId', $metadata]
            )->willReturn($payout);


        /** @var TraitDummyWithCustomerWithParentIF $dummyMock */
        $returnedPayout = $dummyMock->payout(1.2, 'MyCurrency2', 'https://return.url2', $customer);
        $this->assertSame($payout, $returnedPayout);
        $returnedPayout = $dummyMock->payout(1.3, 'MyCurrency3', 'https://return.url3', $customer, 'orderId');
        $this->assertSame($payout, $returnedPayout);
        $returnedPayout = $dummyMock->payout(1.4, 'MyCurrency3', 'https://return.url3', $customer, 'orderId', $metadata);
        $this->assertSame($payout, $returnedPayout);
    }
}
