<?php
/**
 * This file provides an example implementation of the Card payment type.
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
 * @package  UnzerSDK\examples
 */

/** Require the constants of this example */
require_once __DIR__ . '/Constants.php';

/** @noinspection PhpIncludeInspection */
/** Require the composer autoloader file */
require_once __DIR__ . '/../../../../autoload.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Unzer UI Examples</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"
            integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4="
            crossorigin="anonymous"></script>

    <link rel="stylesheet" href="https://static.unzer.com/v1/unzer.css" />
    <script type="text/javascript" src="https://static.unzer.com/v1/unzer.js"></script>
</head>

<body style="margin: 70px 70px 0;">
<h3>Example Data for VISA:</h3>
<ul>
    <li>Number: 4711100000000000</li>
    <li>Expiry date: Date in the future</li>
    <li>Cvc: 123</li>
    <li>Secret: secret3</li>
</ul>

<h3>Example Data for Mastercard:</h3>
<ul>
    <li>Number: 5453010000059543</li>
    <li>Expiry date: Date in the future</li>
    <li>Cvc: 123</li>
    <li>Secret: secret3</li>
</ul>

<p><a href="https://docs.unzer.com/reference/test-data" target="_blank">Click here to open our test data in new tab.</a></p>

<form id="payment-form" class="unzerUI form" novalidate>
    <!-- This is just for the example - Start -->
    <div class="fields inline">
        <label for="transaction_type">Chose the transaction type you want to test:</label>
        <div class="field">
            <div class="unzerUI radio checkbox">
                <input type="radio" name="transaction_type" value="authorize" checked="">
                <label>Authorize</label>
            </div>
        </div>
        <div class="field">
            <div class="unzerUI radio checkbox">
                <input type="radio" name="transaction_type" value="charge">
                <label>Charge</label>
            </div>
        </div>
        <div class="field">
            <div class="unzerUI radio checkbox">
                <input type="radio" name="transaction_type" value="payout">
                <label>Payout</label>
            </div>
        </div>
    </div>
    <div class="fields inline">
        <label for="3dsecure">Select this if you want to try out Card with 3Dsecure:</label>
        <div class="field">
            <div class="unzerUI checkbox">
                <input type="hidden" name="3dsecure" value="0">
                <input type="checkbox" name="3dsecure" value="1">
                <label>Enable 3Ds</label>
            </div>
        </div>
    </div>
    <!-- This is just for the example - End -->

    <div class="field">
        <div id="card-element-id-number" class="unzerInput">
            <!-- Card number UI Element will be inserted here. -->
        </div>
    </div>
    <div class="two fields">
        <div class="field ten wide">
            <div id="card-element-id-expiry" class="unzerInput">
                <!-- Card expiry date UI Element will be inserted here. -->
            </div>
        </div>
        <div class="field six wide">
            <div id="card-element-id-cvc" class="unzerInput">
                <!-- Card CVC UI Element will be inserted here. -->
            </div>
        </div>
    </div>
    <div class="field" id="error-holder" style="color: #9f3a38"></div>
    <div class="field">
        <button class="unzerUI primary button fluid" id="submit-button" type="submit">Pay</button>
    </div>
</form>

<script>
    // Create an Unzer instance with your public key
    let unzerInstance = new unzer('<?php echo UNZER_PAPI_PUBLIC_KEY; ?>');

    // Create a Card instance and render the input fields
    let Card = unzerInstance.Card();
    Card.create('number', {
        containerId: 'card-element-id-number',
        onlyIframe: false
    });
    Card.create('expiry', {
        containerId: 'card-element-id-expiry',
        onlyIframe: false
    });
    Card.create('cvc', {
        containerId: 'card-element-id-cvc',
        onlyIframe: false
    });

    // General event handling
    let formFieldValid = {};
    let payButton = document.getElementById("submit-button");
    let $errorHolder = $('#error-holder');

    // Enable pay button initially
    payButton.disabled = true;

    let eventHandlerCardInput = function(e) {
        if (e.success) {
            formFieldValid[e.type] = true;
            $errorHolder.html('')
        } else {
            formFieldValid[e.type] = false;
        }
        payButton.disabled = !(formFieldValid.number && formFieldValid.expiry && formFieldValid.cvc);
    };

    Card.addEventListener('change', eventHandlerCardInput);

    // Handling the form submission
    let form = document.getElementById('payment-form');
    form.addEventListener('submit', function(event) {
        event.preventDefault();
        // Creating a Card resource
        Card.createResource()
            .then(function(result) {
                let hiddenInput = document.createElement('input');
                hiddenInput.setAttribute('type', 'hidden');
                hiddenInput.setAttribute('name', 'resourceId');
                hiddenInput.setAttribute('value', result.id);
                form.appendChild(hiddenInput);
                form.setAttribute('method', 'POST');
                form.setAttribute('action', '<?php echo CONTROLLER_URL; ?>');

                // Submitting the form
                form.submit();
            })
            .catch(function(error) {
                $errorHolder.html(error.message);
            })
    });
</script>
</body>
</html>
