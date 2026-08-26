document.querySelectorAll('[data-loading-button]').forEach((button) => {
    button.closest('form')?.addEventListener('submit', () => {
        button.disabled = true;
        button.querySelector('[data-loading-label]').textContent = 'Atualizando odds…';
    });
});
