document.addEventListener('DOMContentLoaded', ()=>{
    const toggler = document.querySelector('.unzer-content-toggler');
    if(!toggler){
        return;
    }
    const target = document.querySelector(toggler.getAttribute('data-target'));
    toggler.addEventListener('click', (e)=>{
        e.preventDefault();
        toggler.classList.toggle('active');
        target.style.display = target.style.display === 'none'?'':'none';
    });

    const savePaymentInstrumentSelect = document.querySelector('#unzer-paymentsunzer_card_save_instruments, #unzer-paymentsunzer_paypal_save_instruments, #unzer-paymentsunzer_paypal_save_instruments');
    if(savePaymentInstrumentSelect){
        savePaymentInstrumentSelect.addEventListener('change', (e)=>{
            const value = e.target.value;
            if(value === 'no') {
                alert(unzer_i18n.deletePaymentInstrumentsWarning);
            }
        });
    }
});

