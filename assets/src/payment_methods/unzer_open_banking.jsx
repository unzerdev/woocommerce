import unzerRegisterMethod from "../util/register-method";

const nameInSnakeCase = 'open_banking';
const settings = window.wc.wcSettings.getSetting('unzer_' + nameInSnakeCase + '_data', {})
const gatewayTitle = settings.title || nameInSnakeCase;
const gatewayName = settings.id || nameInSnakeCase;
const gatewayDescription = settings?.description || ''
const publicKey = settings?.publicKey || '';
const locale = settings?.locale || '';
const paymentComponentId = settings.paymentComponentId || (gatewayName + '-payment-component');

const PaymentContent = () => {
    return (
        <div>
            {gatewayDescription && <p>{gatewayDescription}</p>}
            <unzer-payment
                id={paymentComponentId}
                publicKey={publicKey}
                locale={locale}
            >
                <unzer-open-banking></unzer-open-banking>
            </unzer-payment>
        </div>
    )
}
unzerRegisterMethod(gatewayName, gatewayTitle, <PaymentContent/>);

