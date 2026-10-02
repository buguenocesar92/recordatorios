/**
 * Arma la tarjeta de video de FilePond marcando con `.has-video-preview` los
 * `.filepond--root` que ya tienen un <video> adentro: el CSS de
 * muni-ui-filament.css usa esa clase para rearmar el preview (ver el bloque
 * "FILEPOND: TARJETA DE VIDEO" de resources/css/muni-ui-filament.css). Sin
 * este observador, la clase nunca se pone y esas reglas de CSS no hacen nada.
 *
 * FilePond agrega el <video> de forma dinámica —al subir un archivo o al
 * abrir un registro que ya trae uno—, nunca en la carga inicial de la
 * página: por eso hace falta un MutationObserver vigilando el <body>, no
 * alcanza con revisar una sola vez al cargar.
 *
 * Este archivo se inyecta con:
 *   <script data-navigate-once src="…/vendor/muni-ui/filepond-video.js"></script>
 * (ver MuniPanel::register()). El atributo `data-navigate-once` en ESA
 * etiqueta es imprescindible y no es cosa de esta hoja de JS: sin él, cada
 * navegación por wire:navigate vuelve a ejecutar este script y registra un
 * observador nuevo encima del anterior en vez de reemplazarlo, así que la
 * tarjeta de video se arma en la primera pantalla y nunca más en las
 * siguientes. Verificado en el navegador, no en las pruebas.
 */
(function () {
    var observer;

    function marcarRootsConVideo() {
        document.querySelectorAll('.filepond--root').forEach(function (root) {
            root.classList.toggle('has-video-preview', !!root.querySelector('video'));
        });
    }

    function observar() {
        if (observer) {
            observer.disconnect();
        }

        marcarRootsConVideo();

        observer = new MutationObserver(marcarRootsConVideo);
        observer.observe(document.body, { childList: true, subtree: true });
    }

    observar();

    // Livewire reemplaza el <body> entero al navegar por wire:navigate: un
    // observador que sigue apuntando al <body> viejo queda vigilando un nodo
    // ya desechado, así que hay que re-observar el nuevo cada vez que
    // Livewire termina de navegar.
    document.addEventListener('livewire:navigated', observar);
})();
