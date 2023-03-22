<?php

namespace UnzerPayments\Controllers;

use Exception;
use UnzerPayments\Main;
use UnzerPayments\Services\PaymentService;
use UnzerPayments\Services\WebhookManagementService;
use UnzerSDK\Resources\TransactionTypes\AbstractTransactionType;
use UnzerSDK\Resources\TransactionTypes\Authorization;
use UnzerSDK\Resources\TransactionTypes\Cancellation;
use UnzerSDK\Resources\TransactionTypes\Charge;
use UnzerSDK\Unzer;

class AdminController
{
    const GET_ORDER_TRANSACTIONS_ROUTE_SLUG = 'admin_unzer_get_order_transactions';
    const CHARGE_ROUTE_SLUG = 'admin_unzer_charge';
    const WEBHOOK_MANAGEMENT_ROUTE_SLUG = 'admin_unzer_webhooks';
    const KEY_VALIDATION_ROUTE_SLUG = 'admin_unzer_key_validation';

    public function getOrderTransactions()
    {
        try {
            if (!current_user_can('edit_shop_orders') || empty($_GET['order_id'])) {
                wp_die();
            }

            $paymentId = get_post_meta($_GET['order_id'], Main::ORDER_META_KEY_PAYMENT_ID, true);
            if (empty($paymentId)) {
                $this->renderJson([]);
            }

            $unzer = new Unzer(get_option('unzer_private_key'));
            $payment = $unzer->fetchPayment($paymentId);
            $currency = $payment->getCurrency();
            $transactions = [];
            if ($payment->getAuthorization()) {
                $transactions[] = $payment->getAuthorization();
                if ($payment->getAuthorization()->getCancellations()) {
                    $transactions = array_merge($transactions, $payment->getAuthorization()->getCancellations());
                }
            }
            if ($payment->getCharges()) {
                foreach ($payment->getCharges() as $charge) {
                    $transactions[] = $charge;
                    if ($charge->getCancellations()) {
                        $transactions = array_merge($transactions, $charge->getCancellations());
                    }
                }
            }
            if($payment->getReversals()){
                foreach ($payment->getReversals() as $reversal) {
                    $transactions[] = $reversal;
                }
            }
            if($payment->getRefunds()){
                foreach ($payment->getRefunds() as $refund) {
                    $transactions[] = $refund;
                }
            }
            //$transactions = array_merge($transactions, $payment->getCharges(), $payment->getRefunds(), $payment->getReversals());
            $transactionTypes = [
                Cancellation::class => 'cancellation',
                Charge::class => 'charge',
                Authorization::class => 'authorization',
            ];

            $transactions = array_map(function (AbstractTransactionType $transaction) use ($transactionTypes, $currency) {
                $return = $transaction->expose();
                $class = get_class($transaction);
                $return['type'] = $transactionTypes[$class] ?? $class;
                $return['time'] = $transaction->getDate();
                if (method_exists($transaction, 'getAmount') && method_exists($transaction, 'getCurrency')) {
                    $return['amount'] = wc_price($transaction->getAmount(), ['currency' => $transaction->getCurrency()]);
                }elseif (isset($return['amount'])){
                    $return['amount'] = wc_price($return['amount'], ['currency' => $currency]);
                }
                $status = $transaction->isSuccess() ? 'success' : 'error';
                $status = $transaction->isPending() ? 'pending' : $status;
                $return['status'] = $status;

                return $return;
            }, $transactions);
            usort($transactions, function ($a, $b) {
                return strcmp($a['time'], $b['time']);
            });

            $data = [
                'id' => $payment->getId(),
                'amount' => wc_price($payment->getAmount()->getTotal(), ['currency' => $payment->getAmount()->getCurrency()]),
                'charged' => wc_price($payment->getAmount()->getCharged(), ['currency' => $payment->getAmount()->getCurrency()]),
                'cancelled' => wc_price($payment->getAmount()->getCanceled(), ['currency' => $payment->getAmount()->getCurrency()]),
                'remaining' => wc_price($payment->getAmount()->getRemaining(), ['currency' => $payment->getAmount()->getCurrency()]),
                'remainingPlain' => $payment->getAmount()->getRemaining(),
                'transactions' => $transactions,
                'raw' => print_r($payment, true),
            ];

            $this->renderJson($data);
        } catch (Exception $e) {
            $this->renderJson([
                'error' => $e->getMessage(),
            ]);
        }
    }


    public function doCharge()
    {
        $orderId = (int)$_POST['order_id'];
        $amount = isset($_POST['amount'])?(float)$_POST['amount']:null;
        try {
            (new PaymentService())->performChargeOnAuthorization($orderId, $amount);
        } catch (Exception $e) {
            $this->renderJson(['error' => $e->getMessage()]);
        }
        $this->renderJson(['success' => 1]);
    }

    public function webhookManagement()
    {
        $service = new WebhookManagementService();
        try {
            if (empty($_POST['action'])) {
                $this->renderJson([
                    'webhooks' => $service->fetchAllWebhooks(),
                    'isRegistered' => $service->isWebhookRegistered(),
                ]);
            } else {
                switch ($_POST['action']) {
                    case 'delete':
                        $service->deleteWebhook($_POST['id']);
                        break;
                    case 'add':
                        $service->addCurrentWebhook();
                        break;
                }
            }
        } catch (Exception $e) {
            $this->renderJson([
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function validateKeypair(){
        $paymentService = new PaymentService();
        $unzerManager = $paymentService->getUnzerManager();
        try {
            $keyPair = $unzerManager->fetchKeypair();
            if(!empty($keyPair->getPublicKey()) && $keyPair->getPublicKey() === get_option('unzer_public_key')) {
                $this->renderJson([
                    'isValid' => 1,
                ]);
            }else{
                throw new Exception();
            }
        }catch (Exception $e){
            $this->renderJson([
                'isValid' => 0,
            ]);
        }
    }

    public static function renderTransactionTable()
    {
        include UNZER_PLUGIN_PATH . 'html/admin/transactions.php';
    }

    public static function renderGlobalSettingsStart(){
        if (empty($_GET['section']) || $_GET['section'] !== 'unzer_general') {
            return;
        }
        include UNZER_PLUGIN_PATH . 'html/admin/global-settings-start.php';
    }

    public static function renderGlobalSettingsEnd(){
        if (empty($_GET['section']) || $_GET['section'] !== 'unzer_general') {
            return;
        }
        include UNZER_PLUGIN_PATH . 'html/admin/global-settings-end.php';
    }

    public static function renderWebhookManagement()
    {
        if (empty($_GET['section']) || $_GET['section'] !== 'unzer_general') {
            return;
        }
        include UNZER_PLUGIN_PATH . 'html/admin/webhooks.php';
    }

    protected function renderJson(array $data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        die;
    }
}