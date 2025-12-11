import unzerRegisterMethod from "../util/register-method";
import {getSetting} from '@woocommerce/settings'
import { useEffect, useState } from '@wordpress/element'
import onPaymentSetupFactory from "../util/on-payment-setup-factory";

const nameInSnakeCase = 'google_pay';
const settings = getSetting('unzer_' + nameInSnakeCase + '_data', {})
const gatewayTitle = settings.title || nameInSnakeCase;
const gatewayName = settings.id || nameInSnakeCase;
const gatewayDescription = settings?.description || ''
const publicKey = settings?.publicKey || '';
const locale = settings?.locale || '';
const paymentComponentId = settings.paymentComponentId || (gatewayName + '-payment-component');

const PaymentContent = ({eventRegistration, emitResponse}) => {
    const {onPaymentSetup} = eventRegistration;
    useEffect(() => {
        const unsubscribe = onPaymentSetup(onPaymentSetupFactory(paymentComponentId, 'unzer-google-pay-id', emitResponse, settings));
        return unsubscribe
    }, [onPaymentSetup])

    return (
        <div>
            {gatewayDescription && <p>{gatewayDescription}</p>}
            <unzer-payment
                id={paymentComponentId}
                publicKey={publicKey}
                locale={locale}
            >
                <unzer-google-pay></unzer-google-pay>
            </unzer-payment>
        </div>
    )
}

unzerRegisterMethod(gatewayName, gatewayTitle, <PaymentContent/>);
