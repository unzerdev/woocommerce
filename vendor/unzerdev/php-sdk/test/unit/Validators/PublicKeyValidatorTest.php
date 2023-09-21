<?php

/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpDocMissingThrowsInspection */
/**
 * This class defines unit tests to verify functionality of the key validator.
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

namespace UnzerSDK\test\unit\Validators;

use UnzerSDK\test\BasePaymentTest;
use UnzerSDK\Validators\PublicKeyValidator;

class PublicKeyValidatorTest extends BasePaymentTest
{
    /**
     * Verify validate method behaves as expected.
     *
     * @test
     *
     * @dataProvider validateShouldReturnTrueIfPublicKeyHasCorrectFormatDP
     *
     * @param string $key
     * @param bool   $expectedResult
     */
    public function validateShouldReturnTrueIfPublicKeyHasCorrectFormat($key, $expectedResult): void
    {
        $this->assertEquals($expectedResult, PublicKeyValidator::validate($key));
    }

    /**
     * Data provider for above test.
     *
     * @return array
     */
    public function validateShouldReturnTrueIfPublicKeyHasCorrectFormatDP(): array
    {
        return [
            'valid sandbox' => ['s-pub-2a102ZMq3gV4I3zJ888J7RR6u75oqK3n', true],
            'valid production' => ['p-pub-2a102ZMq3gV4I3zJ888J7RR6u75oqK3n', true],
            'invalid public' => ['s-priv-2a10ifVINFAjpQJ9qW8jBe5OJPBx6Gxa', false],
            'invalid wrong format #1' => ['spub-2a10an6aJK0Jg7sMdpu9gK7ih8pCccze', false],
            'invalid empty' => ['', false],
            'invalid null' => [null, false],
            'invalid missing postfix' => ['s-pub-', false],
            'invalid missing type' => ['s--2a10an6aJK0Jg7sMdpu9gK7ih8pCccze', false],
            'invalid wrong type' => ['s-foo-2a10an6aJK0Jg7sMdpu9gK7ih8pCccze', false]
        ];
    }
}
