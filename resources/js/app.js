// PWA: registra el service worker y guarda el aviso de instalación del
// navegador para que el banner «Instalar app» pueda mostrarlo después.
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    window.__pwaInstallPrompt = event;
    window.dispatchEvent(new CustomEvent('pwa-installable'));
});

window.addEventListener('appinstalled', () => {
    window.__pwaInstallPrompt = null;
});
