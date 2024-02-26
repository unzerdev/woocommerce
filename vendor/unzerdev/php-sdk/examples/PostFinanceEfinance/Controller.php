<?php
/**
 * This is the controller for the PostFinanceEfinance example.
 * It is called when the pay button on the index page is clicked.
 *
 * @link  https://docs.unzer.com/
 *
 */

/** Require the constants of this example */
require_once __DIR__ . '/Constants.php';

/** @noinspection PhpIncludeInspection */
/** Require the composer autoloader file */
require_once __DIR__ . '/../../../../autoload.php';

use UnzerSDK\examples\ExampleDebugHandler;
use UnzerSDK\Exceptions\UnzerApiException;
use UnzerSDK\Unzer;

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
if (!isset($_POST['resourceId'])) {
    redirect(FAILURE_URL, 'Resource id is missing!', $clientMessage);
}
$paymentTypeId   = $_POST['resourceId'];

// Catch API errors, write the message to your log and show the ClientMessage to the client.
try {
    // Create an Unzer object using your private key and register a debug handler if you want to.
    $unzer = new Unzer(UNZER_PAPI_PRIVATE_KEY);
    $unzer->setDebugMode(true)->setDebugHandler(new ExampleDebugHandler());

    // Create a charge transaction to get the redirectUrl.
    $transaction = new \UnzerSDK\Resources\TransactionTypes\Charge(12.32, 'CHF', RETURN_CONTROLLER_URL);
    $unzer->performCharge($transaction, $paymentTypeId);

    // You'll need to remember the paymentId for later in the ReturnController
    $_SESSION['PaymentId'] = $transaction->getPaymentId();
    $_SESSION['ShortId']   = $transaction->getShortId();

    // Redirect to the PostFinance page
    if (!$transaction->isError() && $transaction->getRedirectUrl() !== null) {
        redirect($transaction->getRedirectUrl());
    }

    // Check the result message of the charge to find out what went wrong.
    $merchantMessage = $transaction->getMessage()->getCustomer();
} catch (UnzerApiException $e) {
    $merchantMessage = $e->getMerchantMessage();
    $clientMessage = $e->getClientMessage();
} catch (RuntimeException $e) {
    $merchantMessage = $e->getMessage();
}
redirect(FAILURE_URL, $merchantMessage, $clientMessage);
