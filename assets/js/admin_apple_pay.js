document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.apple-pay-remove-key').forEach((el) => {
        el.addEventListener('click', (e) => {
            e.preventDefault();
            const key = el.getAttribute('data-key');
            const formData = new FormData();
            formData.append('key', key);
            fetch(el.href, {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('unzer_apple_pay_' + key + '_preview').remove();
                        el.remove();
                    }
                });
        });
    });

    if (unzerApplePayValidationUrl) {
        fetch(unzerApplePayValidationUrl)
            .then(response => response.json())
            .then(data => {
                for (const key in data.status) {
                    const container = document.getElementById(key + '_status');
                    if (container) {
                        const statusIndicator = container.querySelector('.unzer-status-circle');
                        const message = container.querySelector('.unzer-status-text');

                        if (data.status[key] === 0) {
                            statusIndicator.classList.remove('success');
                            statusIndicator.classList.add('error');
                        } else {
                            statusIndicator.classList.remove('error');
                            statusIndicator.classList.add('success');
                        }
                        if (typeof data.messages[key] !== 'undefined') {
                            message.innerHTML = data.messages[key];
                        }
                    }
                }
            });
    }
});