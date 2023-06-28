<?php
/**
 * This is the controller for the Hosted Payment Page example.
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

use UnzerSDK\Constants\Salutations;
use UnzerSDK\examples\ExampleDebugHandler;
use UnzerSDK\Exceptions\UnzerApiException;
use UnzerSDK\Resources\EmbeddedResources\Address;
use UnzerSDK\Unzer;
use UnzerSDK\Resources\Basket;
use UnzerSDK\Resources\CustomerFactory;
use UnzerSDK\Resources\EmbeddedResources\BasketItem;
use UnzerSDK\Resources\PaymentTypes\Paypage;

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

// These lines are just for this example
$transactionType = $_POST['transaction_type'] ?? 'authorize';

// Catch API errors, write the message to your log and show the ClientMessage to the client.
try {
    // Create an Unzer object using your private key and register a debug handler if you want to.
    $unzer = new Unzer(UNZER_PAPI_PRIVATE_KEY);
    $unzer->setDebugMode(true)->setDebugHandler(new ExampleDebugHandler());

    // A customer with matching addresses is mandatory for Installment payment type
    $address  = (new Address())
        ->setName('Max Mustermann')
        ->setStreet('Vangerowstr. 18')
        ->setCity('Heidelberg')
        ->setZip('69155')
        ->setCountry('DE');

    // Create a charge/authorize transaction
    $customer = CustomerFactory::createCustomer('Max', 'Mustermann')
        ->setSalutation(Salutations::MR)
        ->setBirthDate('2000-02-12')
        ->setLanguage('de')
        ->setEmail('test@test.com');

    // These are the mandatory parameters for the payment page ...
    $paypage = new Paypage(119.00, 'EUR', RETURN_CONTROLLER_URL);

    $orderId = 'o' . str_replace(['0.', ' '], '', microtime(false));

    // ... however you can customize the Payment Page using additional parameters.
    $paypage->setShopName('My Test Shop')
        ->setShopDescription('Best shop in the whole world!')
        ->setTagline('Try and stop us from being awesome!')
        ->setOrderId('OrderNr' . $orderId)
        ->setTermsAndConditionUrl('https://www.unzer.com/en/')
        ->setPrivacyPolicyUrl('https://www.unzer.com/de/datenschutz/')
        ->setImprintUrl('https://www.unzer.com/de/impressum')
        ->setHelpUrl('https://www.unzer.com/de/support')
        ->setContactUrl('https://www.unzer.com/en/ueber-unzer')
        ->setFullPageImage(UNZER_PP_FULL_PAGE_IMAGE_URL)
        ->setLogoImage(UNZER_PP_LOGO_URL)
        ->setInvoiceId('i' . microtime(true));

    // ... in order to enable Unzer Instalment you will need to set the effectiveInterestRate as well.
    $paypage->setEffectiveInterestRate(4.99);

    // ... a Basket is mandatory for InstallmentSecured
    $basketItem = (new BasketItem())
        ->setAmountPerUnitGross(119.00)
        ->setVat(19.00)
        ->setQuantity(1)
        ->setBasketItemReferenceId('item1')
        ->setTitle('Hat');

    $basket = new Basket($orderId);
    $basket->setTotalValueGross(119.00)
        ->addBasketItem($basketItem)
        ->setCurrencyCode('EUR');

    if ($transactionType === 'charge') {
        $unzer->initPayPageCharge($paypage, $customer, $basket);
    } else {
        // For demo purpose we set customer address for authorize only to enable instalment payment.
        // That way e.g. Invoice secured will display customer form on payment page.
        $customer
            ->setShippingAddress($address)
            ->setBillingAddress($address);
        $unzer->initPayPageAuthorize($paypage, $customer, $basket);
    }

    $_SESSION['PaymentId'] = $paypage->getPaymentId();

    // Redirect to the paypage
    redirect($paypage->getRedirectUrl());

} catch (UnzerApiException $e) {
    $merchantMessage = $e->getMerchantMessage();
    $clientMessage = $e->getClientMessage();
} catch (RuntimeException $e) {
    $merchantMessage = $e->getMessage();
}
redirect(FAILURE_URL, $merchantMessage, $clientMessage);
