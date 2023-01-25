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
});