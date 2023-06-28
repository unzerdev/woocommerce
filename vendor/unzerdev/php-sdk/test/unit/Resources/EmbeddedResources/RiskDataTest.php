<?php
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpDocMissingThrowsInspection */
/**
 * This class defines unit tests to verify functionality of the embedded riskData resource.
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
 * @package  UnzerSDK\test\unit
 */
namespace UnzerSDK\test\unit\Resources\EmbeddedResources;

use UnzerSDK\Resources\EmbeddedResources\RiskData;
use UnzerSDK\test\BasePaymentTest;

class RiskDataTest extends BasePaymentTest
{
    /**
     * Verify setter and getter functionalities.
     *
     * @test
     *
     */
    public function settersAndGettersShouldWork(): void
    {
        $riskData = new RiskData();
        $this->assertNull($riskData->getRegistrationDate());
        $this->assertNull($riskData->getRegistrationLevel());
        $this->assertNull($riskData->getThreatMetrixId());
        $this->assertNull($riskData->getConfirmedAmount());
        $this->assertNull($riskData->getConfirmedAmount());
        $this->assertNull($riskData->getCustomerGroup());
        $this->assertNull($riskData->getCustomerId());

        $resp = [
            "threatMetrixId"=> "f544if49wo4f74ef1x",
            "customerGroup"=>"TOP",
            "customerId"=>"C-122345",
            "confirmedAmount"=>"2569",
            "confirmedOrders"=>"14",
            "registrationLevel"=>"1",
            "registrationDate"=>"20160412"
        ];
        $riskData->handleResponse((object)$resp);

        $this->assertEquals('f544if49wo4f74ef1x', $riskData->getThreatMetrixId());
        $this->assertEquals('TOP', $riskData->getCustomerGroup());
        $this->assertEquals('C-122345', $riskData->getCustomerId());
        $this->assertEquals(2569, $riskData->getConfirmedAmount());
        $this->assertEquals(14, $riskData->getConfirmedOrders());
        $this->assertEquals('1', $riskData->getRegistrationLevel());
        $this->assertEquals('20160412', $riskData->getRegistrationDate());
    }
}
