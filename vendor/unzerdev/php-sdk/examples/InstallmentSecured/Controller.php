<?php
/**
 * This is the controller for the Installment Secured example.
 * It is called when the pay button on the index page is clicked.
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

use UnzerSDK\examples\ExampleDebugHandler;
use UnzerSDK\Exceptions\UnzerApiException;
use UnzerSDK\Unzer;
use UnzerSDK\Resources\Basket;
use UnzerSDK\Resources\CustomerFactory;
use UnzerSDK\Resources\EmbeddedResources\Address;
use UnzerSDK\Resources\EmbeddedResources\BasketItem;
use UnzerSDK\Resources\PaymentTypes\InstallmentSecured;

session_start();
session_unset();

$clientMessage = 'Something went wrong. Please try again later.';
$merchantMessage = 'Something went wrong. Please try again later.';

function redirect($url, $merchantMessage = '', $clientMessage = '')
{
    $_SESSION['merchantMessage'] = $merchantMessage;
    $_SESSION['clientMessage']   = $clientMessage;
    header('Location: ' . $url);
    die();
}

// You will need the id of the payment type created in the frontend (index.php)
if (!isset($_POST['paymentTypeId'])) {
    redirect(FAILURE_URL, 'Payment type id is missing!', $clientMessage);
}
$paymentTypeId   = $_POST['paymentTypeId'];

// Catch API errors, write the message to your log and show the ClientMessage to the client.
/** @noinspection BadExceptionsProcessingInspection */
try {
    // Create an Unzer object using your private key and register a debug handler if you want to.
    $unzer = new Unzer(UNZER_PAPI_PRIVATE_KEY);
    $unzer->setDebugMode(true)->setDebugHandler(new ExampleDebugHandler());

    // Use the quote or order id from your shop
    $orderId = 'o' . str_replace(['0.', ' '], '', microtime(false));

    /** @var InstallmentSecured $paymentType */
    $paymentType = $unzer->fetchPaymentType($paymentTypeId);

    // A customer with matching addresses is mandatory for Installment payment type
    $address  = (new Address())
        ->setName('Linda Heideich')
        ->setStreet('Vangerowstr. 18')
        ->setCity('Heidelberg')
        ->setZip('69155')
        ->setCountry('DE');
    $customer = CustomerFactory::createCustomer('Linda', 'Heideich')
        ->setBirthDate('2000-02-12')
        ->setBillingAddress($address)
        ->setShippingAddress($address)
        ->setEmail('linda.heideich@test.de');

    // A Basket is mandatory for Installment Secured payment type
    $basketItem = (new BasketItem())
        ->setAmountPerUnitGross(119.00)
        ->setVat(19)
        ->setQuantity(1)
        ->setBasketItemReferenceId('item1')
        ->setTitle('Hat');

    $basket = new Basket($orderId);
    $basket->setTotalValueGross(119.00)
        ->addBasketItem($basketItem)
        ->setCurrencyCode('EUR');

    // initialize the payment
    $authorize = $unzer->authorize(
        $paymentType->getTotalPurchaseAmount(),
        'EUR',
        $paymentType,
        CONTROLLER_URL,
        $customer,
        $orderId,
        null,
        $basket
    );

    // You'll need to remember the shortId to show it on the success or failure page
    $_SESSION['PaymentId'] = $authorize->getPaymentId();

    // Redirect to the success or failure depending on the state of the transaction
    if ($authorize->isSuccess()) {
        redirect(CONFIRM_URL);
    }

    // Check the result message of the transaction to find out what went wrong.
    $merchantMessage = $authorize->getMessage()->getCustomer();
} catch (UnzerApiException $e) {
    $merchantMessage = $e->getMerchantMessage();
    $clientMessage = $e->getClientMessage();
} catch (RuntimeException $e) {
    $merchantMessage = $e->getMessage();
}
redirect(FAILURE_URL, $merchantMessage, $clientMessage);
