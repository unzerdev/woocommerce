<?php
/**
 * This provides validation functions concerning public key.
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
 * @package  UnzerSDK\Validators
 */

namespace UnzerSDK\Validators;

use function count;

class PublicKeyValidator
{
    /**
     * Returns true if the given public key has a valid format.
     *
     * @param string|null $key
     *
     * @return bool
     */
    public static function validate(?string $key): bool
    {
        $match = [];
        if ($key === null) {
            return false;
        }
        preg_match('/^[sp]-pub-[a-zA-Z0-9]+/', $key, $match);
        return count($match) > 0;
    }
}
