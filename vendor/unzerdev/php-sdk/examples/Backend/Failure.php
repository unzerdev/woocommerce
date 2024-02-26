<?php
/**
 * This is the failure page for backend transactions.
 *
 * @link  https://docs.unzer.com/
 *
 */

session_start();
$isAuthorizeTransaction = $_SESSION['isAuthorizeTransaction'] ?? false;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Unzer UI Examples</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"
            integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/semantic-ui/2.4.1/semantic.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/semantic-ui/2.4.1/semantic.min.css"/>
</head>
    <body style="margin: 70px 70px 0;">
    <div class="ui container segment">
        <h1 id="result">Failure</h1>
        <p>
            There has been an error performing the transaction
            <?php
            if (isset($_SESSION['merchantMessage']) && !empty($_SESSION['merchantMessage'])) {
                echo '<p><strong>Merchant message (don\'t show this to the customer):</strong> ' . $_SESSION['merchantMessage'] . '</p>';
            }
            if (isset($_SESSION['clientMessage']) && !empty($_SESSION['clientMessage'])) {
                echo '<p><strong>Client message (this is the error message for the customer):</strong> ' . $_SESSION['clientMessage'] . '</p>';
            }
            if (isset($_SESSION['ShortId']) && !empty($_SESSION['ShortId'])) {
                echo '<p>Please look for ShortId ' . $_SESSION['ShortId'] . ' in Unzer Insights to see the transaction.</p>';
            }
            if (isset($_SESSION['PaymentId']) && !empty($_SESSION['PaymentId'])) {
                echo '<p>The PaymentId of your transaction is \'' . $_SESSION['PaymentId'] . '\'.</p>';
            }
            echo '<p>Back to <a href="ManagePayment.php">Manage Payment</a> page.</p>';
            ?>
        </p>
        <a href=".." class="ui green button">start again</a>
    </div>
    </body>
</html>
