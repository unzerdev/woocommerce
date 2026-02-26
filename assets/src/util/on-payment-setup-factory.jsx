export default function onPaymentSetupFactory(paymentComponentId, paymentTypeIdFieldName, emitResponse, settings) {

    return async () => {
        try {
            const unzerPaymentComponent = document.getElementById(paymentComponentId)
            if(!unzerPaymentComponent) {
                console.error('unzer payment component does not exist: ' + paymentComponentId);
            }

            if(unzerPaymentComponent.woocommercePaymentTypeId){
                const paymentMethodData = {};
                paymentMethodData['unzer_nonce'] = settings.nonce;
                paymentMethodData[paymentTypeIdFieldName] = unzerPaymentComponent.woocommercePaymentTypeId;
                const returnValue = {
                    type: emitResponse?.responseTypes?.SUCCESS,
                    meta: {
                        paymentMethodData
                    }
                };
                console.log(
                    'return previously generated type id (button payment methods)',
                    returnValue
                );

                return returnValue;
            }

            const response = await unzerPaymentComponent.submit();
            if (response.submitResponse) {
                if (response.submitResponse.success === true) {
                    const paymentMethodData = {};
                    paymentMethodData['unzer_nonce'] = settings.nonce;
                    paymentMethodData[paymentTypeIdFieldName] = response.submitResponse.data.id;

                    const returnValue = {
                        type: emitResponse?.responseTypes?.SUCCESS,
                        meta: {
                            paymentMethodData
                        }
                    };

                    return returnValue;
                } else {
                    return {
                        type: emitResponse?.responseTypes?.ERROR,
                        message: response.submitResponse.message || 'General Error'
                    }
                }
            } else {
                return {
                    type: emitResponse?.responseTypes?.ERROR,
                    message: 'General Error 002'
                }
            }
        } catch (error) {
            return {
                type: emitResponse?.responseTypes?.ERROR,
                message: error.message || error.name || 'General Error 003'
            }
        }

    }
}