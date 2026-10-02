<div>
    <style>
        .mfa-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 40px 30px;
            max-width: 440px;
            width: 100%;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
            text-align: center;
            margin: 40px auto;
        }
        /* Acotados a la tarjeta a propósito: sueltos («h2 {…}», «p {…}») pintan
           cualquier h2 y cualquier p de la página que monte el componente. Hoy no
           se nota porque «layouts.app» es una cáscara con un solo slot, y por eso
           mismo la trampa es difícil de ver: aparece el día que ese layout crece
           o que esta pantalla se monta en otra parte. */
        .mfa-card h2 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #38bdf8;
        }
        .mfa-card p {
            font-size: 14px;
            color: #94a3b8;
            line-height: 1.5;
            margin-bottom: 24px;
        }
        .input-group {
            margin-bottom: 20px;
            text-align: left;
        }
        .input-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #94a3b8;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .input-group input {
            width: 100%;
            padding: 14px 16px;
            border-radius: 12px;
            border: 1.5px solid rgba(255, 255, 255, 0.15);
            background: rgba(15, 23, 42, 0.6);
            color: #f8fafc;
            font-size: 20px;
            text-align: center;
            letter-spacing: 0.25em;
            outline: none;
            transition: all 0.2s ease;
        }
        .input-group input:focus {
            border-color: #38bdf8;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.2);
        }
        .error-message {
            color: #f87171;
            font-size: 13px;
            margin-top: 8px;
            font-weight: 500;
        }
        .btn {
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            border: none;
            background: #38bdf8;
            color: #0f172a;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn:hover {
            background: #0ea5e9;
            transform: translateY(-1px);
        }
        .btn:disabled {
            background: #475569;
            color: #94a3b8;
            cursor: not-allowed;
        }
        .mfa-card .btn-reenviar {
            width: 100%;
            margin-top: 14px;
            padding: 12px 16px;
            min-height: 44px;
            background: none;
            border: 1.5px solid rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            font-size: 13.5px;
            font-weight: 600;
            color: #f8fafc;
            cursor: pointer;
        }
        .mfa-card .btn-reenviar:hover {
            border-color: rgba(255, 255, 255, 0.3);
        }
        /* El anillo de `box-shadow` solo (como en el input de arriba) mide
           ~1,55:1 sobre este fondo oscuro y no cumple el 3:1 de foco visible:
           este botón lleva además un outline sólido. */
        .mfa-card .btn-reenviar:focus-visible {
            outline: 3px solid #38bdf8;
            outline-offset: 2px;
        }
        .mfa-card .btn-reenviar[aria-disabled="true"] {
            opacity: 0.6;
            cursor: progress;
        }
        .simulator {
            margin-top: 30px;
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 13.5px;
            color: #f59e0b;
        }
        .simulator strong {
            font-size: 16px;
            letter-spacing: 0.1em;
            display: block;
            margin-top: 4px;
        }
    </style>

    <div class="mfa-card">
        <h2>Código de Seguridad (MFA)</h2>
        {{-- Decir si se envió un código AHORA o si sigue valiendo el anterior. Sin
             esta distinción, quien no recibía el correo recargaba, no le llegaba
             nada —correcto: el código previo seguía sirviendo— y lo leía como que
             el sistema está roto. --}}
        <div role="status">
            @if ($codigoReenviado)
                @if ($correoEnmascarado)
                    <p style="margin-top: -14px;">Te enviamos un código nuevo a <strong>{{ $correoEnmascarado }}</strong>.</p>
                @else
                    <p style="margin-top: -14px;">Te enviamos un código nuevo.</p>
                @endif
            @elseif ($codigoReutilizado)
                <p style="margin-top: -14px;">
                    @if ($correoEnmascarado)
                        El código que enviamos a <strong>{{ $correoEnmascarado }}</strong> sigue siendo válido,
                    @else
                        El código que te enviamos sigue siendo válido,
                    @endif
                    así que no generamos uno nuevo. Revisá ese correo o pedí otro con el botón de abajo.
                </p>
            @else
                <p>Hemos generado un código de verificación de doble factor para proteger tu acceso.</p>
                @if($correoEnmascarado)
                    <p style="margin-top: -14px;">Lo enviamos a <strong>{{ $correoEnmascarado }}</strong>.</p>
                @endif
            @endif
        </div>

        <form wire:submit.prevent="verificar">
            <div class="input-group">
                <label for="code">Código OTP (6 dígitos)</label>
                {{-- `autocomplete="one-time-code"` y no `off`: con `off` el navegador y el
                     sistema tienen PROHIBIDO ofrecer el código que acaba de llegar, que es
                     justo la ayuda que hace falta acá. `inputmode="numeric"` abre el teclado
                     numérico en el teléfono en vez del alfabético — se queda en type="text"
                     porque type="number" trae flechas de incremento y pierde los ceros a la
                     izquierda de un código como 000123. --}}
                <input type="text" id="code" wire:model="code" maxlength="6" inputmode="numeric" autocomplete="one-time-code" placeholder="000000" required x-on:mfa-reenviado.window="$el.focus()">
                @error('code')
                    <div class="error-message">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn" wire:loading.attr="disabled">Verificar Código</button>
        </form>

        {{-- Si el correo se demora o cae en spam, esperar los diez minutos completos
             era la única salida (o recargar, topado por `mfa-envio`). Este botón
             invalida el código anterior y manda uno nuevo (tope de 3 cada 5 minutos
             por cuenta, aplicado en el servidor). --}}
        {{-- `aria-disabled` y no `disabled`: un botón enfocado que pasa a `disabled`
             suelta el foco al cuerpo de la página y no lo recupera. El tope real
             está en el servidor, así que el doble clic no rompe nada. --}}
        <button
            type="button"
            wire:click="reenviar"
            wire:loading.attr="aria-disabled"
            wire:target="reenviar"
            class="btn-reenviar"
        >
            <span wire:loading.remove wire:target="reenviar">Enviar otro código</span>
            <span wire:loading wire:target="reenviar">Enviando…</span>
        </button>

        @if ($avisoDeReenvio)
            <div class="error-message" role="alert">{{ $avisoDeReenvio }}</div>
        @endif

        @if($otpParaMostrar)
            <div class="simulator">
                <strong>Código OTP de Prueba (Simulador):</strong>
                <strong>{{ $otpParaMostrar }}</strong>
                <span style="font-size: 11px; display: block; margin-top: 4px; color: #a1a1aa;">(En producción este código se envía por un canal real)</span>
            </div>
        @endif
    </div>
</div>
