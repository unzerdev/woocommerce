document.addEventListener('DOMContentLoaded', e => {
    document.querySelectorAll('.dismiss-unzer-notification').forEach(el => {
        el.addEventListener('click', e => {
            e.preventDefault();
            el.parentNode.parentNode.remove();
            fetch(el.getAttribute('data-url'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'remove_notification=' + el.getAttribute('data-id')
            });
        });
    });
});