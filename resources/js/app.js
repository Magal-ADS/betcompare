document.querySelectorAll('[data-loading-button]').forEach((button) => {
    button.closest('form')?.addEventListener('submit', () => {
        button.disabled = true;
        button.querySelector('[data-loading-label]').textContent = 'Iniciando atualização…';
    });
});

document.querySelectorAll('[data-event-analysis]').forEach((eventAnalysis) => {
    const marketPicker = eventAnalysis.querySelector('[data-market-picker]');

    marketPicker?.addEventListener('change', () => {
        eventAnalysis.querySelectorAll('[data-market-panel]').forEach((marketPanel) => {
            marketPanel.hidden = marketPanel.dataset.marketPanel !== marketPicker.value;
        });
    });
});

document.querySelectorAll('[data-filter-funnel]').forEach((filterFunnel) => {
    const region = filterFunnel.querySelector('[data-filter-region]');
    const country = filterFunnel.querySelector('[data-filter-country]');
    const competition = filterFunnel.querySelector('[data-filter-competition]');
    const game = filterFunnel.querySelector('[data-filter-game]');

    const filterOptions = (select, isAvailable) => {
        Array.from(select?.options ?? []).slice(1).forEach((option) => {
            const available = isAvailable(option);
            option.hidden = !available;
            option.disabled = !available;

            if (!available && option.selected) {
                select.value = '';
            }
        });
    };

    const synchronize = () => {
        filterOptions(country, (option) => !region.value || option.dataset.region === region.value);
        filterOptions(competition, (option) => {
            return (!region.value || option.dataset.region === region.value)
                && (!country.value || option.dataset.country === country.value);
        });
        filterOptions(game, (option) => {
            return (!region.value || option.dataset.region === region.value)
                && (!country.value || option.dataset.country === country.value)
                && (!competition.value || option.dataset.competition === competition.value);
        });
    };

    region?.addEventListener('change', () => {
        country.value = '';
        competition.value = '';
        game.value = '';
        synchronize();
    });
    country?.addEventListener('change', () => {
        competition.value = '';
        game.value = '';
        synchronize();
    });
    competition?.addEventListener('change', () => {
        game.value = '';
        synchronize();
    });

    synchronize();
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
