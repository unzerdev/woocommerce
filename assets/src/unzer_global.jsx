import {select, subscribe} from '@wordpress/data';

window.unzerCurrentPaymentMethod = '';
window.unzerCurrentAmount = 0;
const updatePaymentMethodData = function (longNameInSnakeCase, cartData) {
    console.info('updatePaymentMethodData for ' + longNameInSnakeCase);
    const settings = window.wc.wcSettings.getSetting(longNameInSnakeCase + '_data', {})
    console.log(settings, cartData);
    setTimeout(async () => {
            const paymentComponent = document.getElementById(settings.paymentComponentId);
            if (paymentComponent) {
                Promise.all([customElements.whenDefined('unzer-payment')]).then(
                    () => {
                        try {
                            const data = new FormData();
                            data.append('data', JSON.stringify(cartData));
                            data.append('payment_method', longNameInSnakeCase);
                            data.append('unzer_nonce', settings.nonce);
                            fetch(settings.getCustomerDataUrl, {
                                method: 'POST',
                                body: data

                            }).then((response) => response.json())
                                .then((responseData)=>{
                                    const customerData = responseData.customer;
                                    console.log(
                                        'set customer data',
                                        customerData
                                    );
                                    if (customerData) {
                                        paymentComponent.setCustomerData(
                                            customerData
                                        );
                                    }

                                    if(responseData.publicKey !== paymentComponent.publicKey) {
                                        paymentComponent.publicKey = responseData.publicKey;
                                        paymentComponent.initOnFirstUpdate();
                                    }

                                    if (paymentComponent.setBasketData) {
                                        paymentComponent.setBasketData(
                                            {
                                                amount: cartData.totals.total_price/100,
                                                currencyType: cartData.totals.currency_code,
                                                country: cartData.billingAddress.country
                                            }
                                        );
                                        const installmentComponent = document.getElementById('unzer-paylater-installment-component');
                                        if (installmentComponent && installmentComponent.reloadPlansWithRetainedData) {
                                            installmentComponent.updateDataFromStore();
                                            console.log(installmentComponent.basketDetails);
                                            installmentComponent.onDataReady();
                                        }
                                    }


                                });

                        } catch (e) {
                            console.error(e);
                        }
                    }
                );

            }
        }, 100
    )

}
subscribe(() => {
    const cartStore = select(window.wc.wcBlocksData.CART_STORE_KEY);
    const paymentStore = select(window.wc.wcBlocksData.PAYMENT_STORE_KEY);
    const cartData = cartStore.getCartData();
    let hasChanged = false;
    if (window.unzerCurrentPaymentMethod !== paymentStore.getActivePaymentMethod()) {
hasChanged = true;
    }

    if(!hasChanged && window.unzerCurrentAmount !== cartData.totals.total_price){
        hasChanged = true;
    }
    if(hasChanged) {
        console.log('payment method changed to: ' + paymentStore.getActivePaymentMethod());
        window.unzerCurrentPaymentMethod = paymentStore.getActivePaymentMethod();
        window.unzerCurrentAmount = cartData.totals.total_price;
        updatePaymentMethodData(window.unzerCurrentPaymentMethod, cartData);
    }
}, 'wc/store/cart');


//
// const paymentMethodUpdateCallbacks = function (shortNameInSnakeCase) {
//     const settings = window.wc.wcSettings.getSetting('unzer_' + shortNameInSnakeCase + '_data', {})
//     console.log(shortNameInSnakeCase, settings);
//     const {checkoutStore} = window.wc.wcBlocksData;
//     console.log('checkoutStore', checkoutStore);
//     const paymentComponent = document.getElementById(settings.paymentComponentId);
//     if (paymentComponent) {
//         console.log('PPPCCCCC', paymentComponent);
//     }
//
//
// }
//
// //we actually don't need this any more, but keep it in case a use case pops up
// const unzerIsPaymentMethodAllowed = (shortNameInSnakeCase, arg) => {
//     console.log(arg);
//     paymentMethodUpdateCallbacks(shortNameInSnakeCase);
//     // console.log(shortNameInSnakeCase);
//     // console.trace();
//     // const settings = window.wc.wcSettings.getSetting('unzer_' + shortNameInSnakeCase + '_data', {})
//     // const currency = arg.cart.cartTotals.currency_code;
//     // const country = arg.billingAddress.country;
//     // if(settings.allowedCountries){
//     //     if(!settings.allowedCountries.includes(country)){
//     //         return false;
//     //     }
//     // }
//     // if(settings.allowedCurrencies){
//     //     if(!settings.allowedCurrencies.includes(currency)){
//     //         return false;
//     //     }
//     // }
//     return true;
// };
//
// const callbackCollection = {};
// for (const simpleMethod of simpleMethods) {
//     callbackCollection['unzer_' + simpleMethod] = (arg) => {
//         return unzerIsPaymentMethodAllowed(simpleMethod, arg);
//     }
// }
// console.log(callbackCollection);
// registerPaymentMethodExtensionCallbacks('unzer-payments', callbackCollection);