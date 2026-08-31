document.querySelectorAll('[data-loading-button]').forEach((button) => {
    button.closest('form')?.addEventListener('submit', () => {
        button.disabled = true;
        button.querySelector('[data-loading-label]').textContent = 'Atualizando odds…';
    });
});

const installButton = document.querySelector('[data-pwa-install]');
let deferredInstallPrompt;

window.addEventListener('beforeinstallprompt', (event) => {
    if (!installButton) {
        return;
    }

    event.preventDefault();
    deferredInstallPrompt = event;
    installButton?.removeAttribute('hidden');
});

installButton?.addEventListener('click', async () => {
    if (!deferredInstallPrompt) {
        return;
    }

    await deferredInstallPrompt.prompt();
    deferredInstallPrompt = undefined;
    installButton.setAttribute('hidden', 'hidden');
});

window.addEventListener('appinstalled', () => {
    deferredInstallPrompt = undefined;
    installButton?.setAttribute('hidden', 'hidden');
});

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js');
    });
}
