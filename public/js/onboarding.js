(function () {
    'use strict';

    var cfg = window.OnboardingConfig || {};
    var BASE = '/api/onboarding';
    var MUTE_KEY = 'onboarding_tts_muted';
    var started = false;
    var running = false;

    var TTS = {
        supported: typeof window.speechSynthesis !== 'undefined',
        _voice: null,
        isMuted: function () { return localStorage.getItem(MUTE_KEY) === '1'; },
        setMuted: function (v) { localStorage.setItem(MUTE_KEY, v ? '1' : '0'); if (v) this.stop(); },
        stop: function () { if (this.supported) { try { window.speechSynthesis.cancel(); } catch (e) {} } },
        pickVoice: function () {
            if (!this.supported) return null;
            var voices = window.speechSynthesis.getVoices() || [];
            var es = voices.filter(function (v) { return /^es/i.test(v.lang); });
            if (!es.length) return null;
            var female = /(m[oó]nica|paulina|helena|sabina|luc[ií]a|laura|esperanza|marisol|montserrat|montse|pen[eé]lope|female|femenin|mujer)/i;
            return es.filter(function (v) { return female.test(v.name); })[0]
                || es.filter(function (v) { return /google/i.test(v.name); })[0]
                || es[0];
        },
        // El servidor entrega el texto escapado (va a innerHTML en el globo del tour).
        // Para leerlo en voz alta hay que deshacer ese escape primero, o «&amp;» se
        // pronuncia «amp». Se deshacen solo las cinco entidades que produce htmlspecialchars,
        // en vez de pasar el texto por el parser de HTML del navegador: menos superficie
        // y no depende de dónde se apoye la decodificación.
        decodificar: function (text) {
            return String(text)
                .replace(/&lt;/g, '<')
                .replace(/&gt;/g, '>')
                .replace(/&quot;/g, '"')
                .replace(/&#0?39;/g, "'")
                .replace(/&amp;/g, '&');
        },
        speak: function (text) {
            if (!this.supported || this.isMuted() || !text) return;
            this.stop();
            var clean = this.decodificar(text).replace(/[^\p{L}\p{N}\s.,;:¿?¡!()%/-]/gu, '').trim();
            if (!clean) return;
            if (!this._voice) this._voice = this.pickVoice();
            var u = new SpeechSynthesisUtterance(clean);
            if (this._voice) { u.voice = this._voice; u.lang = this._voice.lang; }
            else { u.lang = 'es-CL'; }
            u.rate = 0.85;
            u.pitch = 1.0;
            u.volume = 1.0;
            try { window.speechSynthesis.speak(u); } catch (e) {}
        },
    };

    if (TTS.supported) {
        try {
            window.speechSynthesis.onvoiceschanged = function () { TTS._voice = TTS.pickVoice(); };
            TTS._voice = TTS.pickVoice();
        } catch (e) {}
    }

    function getTour(opts) {
        opts = opts || {};
        var qs = [];
        if (cfg.system) qs.push('system=' + encodeURIComponent(cfg.system));
        if (cfg.surface) qs.push('surface=' + encodeURIComponent(cfg.surface));
        if (opts.tourId) qs.push('tour_id=' + encodeURIComponent(opts.tourId));
        return fetch(BASE + '/tour?' + qs.join('&'), {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
        }).then(function (r) { return r.ok ? r.json() : { tour: null }; });
    }

    function postJson(path, body) {
        return fetch(BASE + path, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': cfg.csrfToken || '',
            },
            body: JSON.stringify(body || {}),
        });
    }

    function markComplete(tourId) {
        if (!tourId) return Promise.resolve();
        return postJson('/complete', { tour_id: tourId }).catch(function () {});
    }

    function isVisible(el) { return !!(el && el.offsetParent !== null); }

    function waitForVisible(selector, timeout) {
        return new Promise(function (resolve) {
            if (!selector) return resolve(null);
            var t0 = Date.now();
            (function poll() {
                var el = document.querySelector(selector);
                if (isVisible(el)) return resolve(el);
                if (Date.now() - t0 > timeout) return resolve(el || null);
                setTimeout(poll, 80);
            })();
        });
    }

    function prepareStep(step) {
        if (!step) return Promise.resolve();
        var triggered = false;
        if (step.pre_action_selector) {
            var trg = document.querySelector(step.pre_action_selector);
            if (trg) { trg.click(); triggered = true; }
        }
        if (step.element_selector) {
            return waitForVisible(step.element_selector, triggered ? 2500 : 600);
        }
        return new Promise(function (r) { setTimeout(r, triggered ? 350 : 30); });
    }

    function usableSteps(steps) {
        return (steps || []).filter(function (s) {
            if (!s.element_selector && !s.pre_action_selector) return true;
            if (s.pre_action_selector) return !!document.querySelector(s.pre_action_selector);
            return !!document.querySelector(s.element_selector);
        });
    }

    function toDriverStep(s) {
        var step = { popover: { title: s.title || '', description: s.description || '', side: s.position || 'bottom' } };
        if (s.element_selector) step.element = s.element_selector;
        return step;
    }

    function renderMuteButton(popover) {
        if (!TTS.supported || !popover || !popover.footerButtons) return;
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'onboarding-mute-btn';
        btn.style.cssText = 'margin-right:auto;background:transparent;border:0;cursor:pointer;font-size:18px;line-height:1';
        var sync = function () {
            btn.textContent = TTS.isMuted() ? '🔇' : '🔊';
            btn.title = TTS.isMuted() ? 'Activar voz' : 'Silenciar voz';
        };
        sync();
        btn.addEventListener('click', function (e) {
            e.preventDefault(); e.stopPropagation();
            TTS.setMuted(!TTS.isMuted()); sync();
        });
        popover.footerButtons.insertBefore(btn, popover.footerButtons.firstChild);
    }

    function drive(data, options) {
        options = options || {};
        if (!window.driver || !window.driver.js) {
            console.warn('[onboarding] Driver.js no está cargado.');
            return;
        }
        var steps = usableSteps(data.steps);
        if (!steps.length || running) return;

        var tourId = data.tour ? data.tour.id : null;
        var driverFn = window.driver.js.driver;
        var d;

        d = driverFn({
            // Sin fundido cuando la persona pidió menos movimiento (WCAG 2.3.3).
            // driver.js anima por defecto y marcaba el cuerpo con «driver-fade»
            // aunque el sistema tuviera activa la preferencia; ese fundido era
            // además lo que la batería de accesibilidad atrapaba a medias.
            animate: !(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches),
            showProgress: true,
            allowClose: true,
            nextBtnText: 'Siguiente →',
            prevBtnText: '← Anterior',
            doneBtnText: 'Terminar 🏁',
            progressText: '{{current}} de {{total}}',
            steps: steps.map(toDriverStep),
            onPopoverRender: function (popover) { renderMuteButton(popover); },
            onHighlighted: function (el, step) {
                TTS.speak(step && step.popover ? step.popover.description : '');
            },
            onNextClick: function (el, step, opts) {
                TTS.stop();
                var next = opts.state.activeIndex + 1;
                if (next < steps.length) {
                    prepareStep(steps[next]).then(function () { d.moveNext(); });
                } else {
                    d.moveNext();
                }
            },
            onPrevClick: function (el, step, opts) {
                TTS.stop();
                var prev = opts.state.activeIndex - 1;
                if (prev >= 0) {
                    prepareStep(steps[prev]).then(function () { d.movePrevious(); });
                } else {
                    d.movePrevious();
                }
            },
            onDestroyed: function () {
                running = false;
                TTS.stop();
                if (!options.preview) markComplete(tourId);
            },
        });

        running = true;
        prepareStep(steps[0]).then(function () { d.drive(); });
    }

    function autoStart() {
        if (started || running || cfg.autoStart === false) return;
        getTour().then(function (data) {
            if (!data || !data.tour || data.completed) return;
            started = true;
            setTimeout(function () { drive(data); }, 800);
        });
    }

    function replay() {
        getTour().then(function (data) { if (data && data.tour) drive(data); });
    }

    function preview(tourId) {
        getTour({ tourId: tourId }).then(function (data) { if (data && data.tour) drive(data, { preview: true }); });
    }

    function reset() {
        getTour().then(function (data) {
            if (!data || !data.tour) return;
            postJson('/reset', { tour_id: data.tour.id }).then(function () { drive(data); });
        });
    }

    window.Onboarding = { autoStart: autoStart, replay: replay, preview: preview, reset: reset, tts: TTS };
    window.onboardingPreview = preview;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoStart);
    } else {
        autoStart();
    }
    document.addEventListener('livewire:navigated', function () {
        started = false;
        autoStart();
    });
})();
