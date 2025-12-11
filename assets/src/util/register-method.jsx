import {decodeEntities} from "@wordpress/html-entities";
import { registerPaymentMethod } from '@woocommerce/blocks-registry'

export default function unzerRegisterMethod(gatewayName, gatewayTitle, component) {
    registerPaymentMethod({
        name: gatewayName,
        label: (
            <div>
            <span className='wc-block-components-payment-method-label'>
                {gatewayTitle}
            </span>
            </div>
        ),
        content: component,
        edit: component,
        canMakePayment: () => true,
        ariaLabel: decodeEntities(gatewayTitle),
        supports: {
            features: ['products'],
        }
    })
}

