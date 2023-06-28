<?php
/**
 * This is the controller performing the fetch all command for the Webhook tests.
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

function printMessage($type, $title, $text)
{
    echo '<div class="ui ' . $type . ' message">'.
            '<div class="header">' . $title . '</div>'.
            '<p>' . nl2br($text) . '</p>'.
         '</div>';
}

function printError($text)
{
    printMessage('error', 'Error', $text);
}

function printInfo($title, $text)
{
    printMessage('info', $title, $text);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Unzer UI Examples</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"
            integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4="
            crossorigin="anonymous"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/semantic-ui/2.4.1/semantic.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/semantic-ui/2.4.1/semantic.min.css" />
</head>

<body style="margin: 70px 70px 0;">
<div class="ui container segment">
    <h2 class="ui header">
        <i class="envelope outline icon"></i>
        <span class="content">
            Webhook registration
        </span>
    </h2>

    <?php
        try {
            $unzer = new Unzer(UNZER_PAPI_PRIVATE_KEY);
            $unzer->setDebugMode(true)->setDebugHandler(new ExampleDebugHandler());

            $webhooks = $unzer->fetchAllWebhooks();
        } catch (UnzerApiException $e) {
            printError($e->getMessage());
            $unzer->debugLog('Error: ' . $e->getMessage());
        } catch (RuntimeException $e) {
            printError($e->getMessage());
            $unzer->debugLog('Error: ' . $e->getMessage());
        }

        printInfo('Back to the payment selection', 'Now Perform payments <a href="..">>> HERE <<</a> to trigger events!');
    ?>
</div>
</body>
</html>
