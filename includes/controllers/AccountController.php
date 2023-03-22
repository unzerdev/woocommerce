<?php

namespace UnzerPayments\Controllers;


use UnzerPayments\Gateways\Card;
use UnzerPayments\Gateways\Paypal;
use UnzerPayments\Main;
use UnzerPayments\Services\PaymentService;
use UnzerPayments\Traits\SavePaymentInstrumentTrait;

class AccountController
{
    const DELETE_PAYMENT_INSTRUMENT_URL_SLUG = 'delete_payment_instrument';

    public function deletePaymentInstrument(){
        if(empty($_POST['instrument']) || !is_user_logged_in()){
            return;
        }

        $user = wp_get_current_user();
        $existingPaymentMeans = get_user_meta($user->ID, Main::USER_META_KEY_PAYMENT_INSTRUMENTS, true);
        if (empty($existingPaymentMeans) || !is_array($existingPaymentMeans)) {
            return;
        }

        foreach($existingPaymentMeans as $gatewayClass=>$instruments){
            if(isset($instruments[$_POST['instrument']])){
                unset($existingPaymentMeans[$gatewayClass][$_POST['instrument']]);
            }
        }
        update_user_meta($user->ID, Main::USER_META_KEY_PAYMENT_INSTRUMENTS, $existingPaymentMeans);
    }

    public function accountPaymentInstruments()
    {
        $gateways = [
            Card::class,
            Paypal::class
        ];
        $html = '';
        foreach($gateways as $gateway){
            /** @var Card|Paypal $gatewayObject */
            $gatewayObject = new $gateway;
            $savedInstruments = $gatewayObject->getSavedPaymentInstruments();
            if($savedInstruments && $gatewayObject->isSaveInstruments()){
                $html .= '<div class="unzer-payment-mean"><b>'.$gatewayObject->get_title().'</b></div><ul>';
                foreach($savedInstruments as $savedInstrument){
                    $html .= '<li>'.$savedInstrument['label'].' <a href="#" onclick="'.esc_attr('
                        fetch(\''.WC()->api_request_url(AccountController::DELETE_PAYMENT_INSTRUMENT_URL_SLUG).'\', {
                            method: \'post\',
                            body: \'instrument='.$savedInstrument['id'].'\',
                            headers:{\'Content-Type\': \'application/x-www-form-urlencoded\'}
                        }).then((data)=>{location.reload()});
                        return false;
                        ').'">'.__('Delete', 'unzer-payments').'</a></li>';
                }
                $html .= '</ul>';
            }
        }
        if($html){
            $html = '<h2>'.__('Your saved payment means', 'unzer-payments').'</h2>'.$html;
        }
        echo $html;

    }
}