function unzerProcessSubKeyCheck(container) {
    const url = container.getAttribute('data-url');
    console.log(url);
    fetch(url,
        {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'slug=' + container.getAttribute('data-slug')
        })
        .then(response => response.json())
        .then(data => {
            container.querySelectorAll('.is-success').forEach((el) => {
                el.style.display = data.isValid === 0 ? 'none' : 'block';
            });
            container.querySelectorAll('.is-error').forEach((el) => {
                el.style.display = data.isValid === 0 ? 'block' : 'none';
            });
        });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.unzer-key-status').forEach((el) => {
        unzerProcessSubKeyCheck(el);
    });
});
