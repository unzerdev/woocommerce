export default function onPaymentSetupFactory(paymentComponentId, paymentTypeIdFieldName, emitResponse, settings) {

    return async () => {
        try {
            const unzerPaymentComponent = document.getElementById(paymentComponentId)
            if(!unzerPaymentComponent) {
                console.error('unzer payment component does not exist: ' + paymentComponentId);
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
                    console.log(
                        'submit response: ',
                        response.submitResponse,
                        returnValue
                    );

                    return returnValue;
                } else {
                    return {
                        type: emitResponse?.responseTypes?.ERROR,
                        message: 'GENERAL ERROR 1'
                    }
                }
            } else {
                return {
                    type: emitResponse?.responseTypes?.ERROR,
                    message: 'GENERAL ERROR 2'
                }
            }
        } catch (err) {
            return {
                type: emitResponse?.responseTypes?.ERROR,
                message: 'GENERAL ERROR 3'
            }
        }

    }
}