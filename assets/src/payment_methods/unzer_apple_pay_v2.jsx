import unzerRegisterMethod from "../util/register-method";
import {getSetting} from '@woocommerce/settings'
import {useEffect} from '@wordpress/element'
import onPaymentSetupFactory from "../util/on-payment-setup-factory";

const nameInSnakeCase = 'apple_pay_v2';
const settings = getSetting('unzer_' + nameInSnakeCase + '_data', {})
const gatewayTitle = settings.title || nameInSnakeCase;
const gatewayName = settings.id || nameInSnakeCase;
const gatewayDescription = settings?.description || ''
const publicKey = settings?.publicKey || '';
const locale = settings?.locale || '';
const paymentComponentId = settings.paymentComponentId || (gatewayName + '-payment-component');
const checkoutComponentId = settings.checkoutComponentId || (gatewayName + '-checkout-component');

const PaymentContent = ({eventRegistration, emitResponse}) => {
    const {onPaymentSetup} = eventRegistration;
    useEffect(() => {
        const unsubscribe = onPaymentSetup(onPaymentSetupFactory(paymentComponentId, 'unzer-apple-pay-v2-id', emitResponse, settings));
        return unsubscribe
    }, [onPaymentSetup])

    return (
        <div>
            {gatewayDescription && <p>{gatewayDescription}</p>}
            <div style={{ display: "none" }}>
                <div id={paymentComponentId + "-container-for-button"}>
                    <unzer-payment
                        id={paymentComponentId}
                        publicKey={publicKey}
                        locale={locale}
                    >
                        <unzer-apple-pay></unzer-apple-pay>
                    </unzer-payment>
                    <unzer-checkout id={checkoutComponentId}></unzer-checkout>
                </div>
            </div>
        </div>
    )
}

unzerRegisterMethod(gatewayName, gatewayTitle, <PaymentContent/>);
