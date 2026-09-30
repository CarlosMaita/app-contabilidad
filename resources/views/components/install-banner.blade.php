{{--
    Invitación a instalar la app, solo en pantallas chicas y si todavía no
    está instalada. Android/Chrome usa el aviso nativo del navegador
    (capturado en app.js); iPhone no tiene aviso, así que explica el paso.
--}}
<div x-data="{
        visible: false,
        ios: false,
        init() {
            const standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
            let dismissed = false;
            try { dismissed = localStorage.getItem('pwa-install-dismissed') === '1'; } catch (e) {}
            if (standalone || dismissed) return;

            this.ios = /iphone|ipad|ipod/i.test(navigator.userAgent);
            if (this.ios) { this.visible = true; return; }

            const check = () => { this.visible = !! window.__pwaInstallPrompt; };
            check();
            window.addEventListener('pwa-installable', check);
            window.addEventListener('appinstalled', () => { this.visible = false; });
        },
        async install() {
            const prompt = window.__pwaInstallPrompt;
            if (! prompt) return;
            prompt.prompt();
            await prompt.userChoice;
            window.__pwaInstallPrompt = null;
            this.visible = false;
        },
        dismiss() {
            try { localStorage.setItem('pwa-install-dismissed', '1'); } catch (e) {}
            this.visible = false;
        },
    }"
    x-show="visible" x-cloak
    class="flex items-center gap-3 border-b-2 border-ink bg-white px-4 py-2.5 md:hidden">
    <img src="/icons/icon-192.png" alt="" class="h-8 w-8 shrink-0">
    <div class="min-w-0 grow text-[13px] leading-snug">
        <div class="font-extrabold">Instalá la app en tu teléfono</div>
        <div x-show="ios" class="text-xs text-neutral-700">Tocá Compartir y luego «Agregar a pantalla de inicio».</div>
    </div>
    <button type="button" x-show="! ios" x-on:click="install()" class="btn-primary shrink-0">Instalar</button>
    <button type="button" x-on:click="dismiss()" class="shrink-0 px-1 text-lg leading-none text-neutral-600 hover:text-ink" aria-label="Cerrar">×</button>
</div>
