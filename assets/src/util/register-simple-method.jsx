import unzerRegisterMethod from "./register-method";
import { getSetting } from '@woocommerce/settings'

export default function unzerRegisterSimpleMethod(nameInSnakeCase) {
    const settings = getSetting('unzer_' + nameInSnakeCase + '_data', {})
    const gatewayTitle = settings.title || nameInSnakeCase;
    const gatewayName = settings.id || nameInSnakeCase;
    const gatewayDescription = settings?.description || ''

    const PaymentContent = () => {
        return gatewayDescription ? <p>{gatewayDescription}</p> : null;
    }
    unzerRegisterMethod(gatewayName, gatewayTitle, <PaymentContent />);
}
