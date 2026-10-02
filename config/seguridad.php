<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Límite de intentos de acceso
    |--------------------------------------------------------------------------
    |
    | Se apaga en la batería de pruebas (phpunit.xml): decenas de accesos desde
    | una sola dirección la bloquearían a sí misma, y ese rojo no significaría
    | nada. En cualquier otro entorno va encendido.
    |
    */

    // Encendido en cualquier entorno menos los de trabajo. En local y en la
    // batería de pruebas estorba sin proteger nada: no hay casillas ajenas que
    // cuidar, y una automatización que entra y sale varias veces —las E2E, el
    // grabador de videos— agota el tope y se bloquea a sí misma. Se puede
    // encender a mano con la variable para probarlo.
    'limitar_intentos' => env(
        'SEGURIDAD_LIMITAR_INTENTOS',
        ! in_array(env('APP_ENV', 'production'), ['local', 'testing'], true),
    ),

    /*
    |--------------------------------------------------------------------------
    | Cuánto se puede insistir
    |--------------------------------------------------------------------------
    |
    | Se limita por DOS llaves a la vez, y la razón es concreta: contando solo
    | por dirección de origen, quien insista contra una cuenta deja fuera del
    | panel a toda la organización, porque en una municipalidad todos salen a
    | internet por la misma dirección. Se comprobó en un sistema anterior:
    | bloquear por IP dejaba al funcionario legítimo sin poder entrar, sin
    | explicación y sin haber hecho nada.
    |
    | Por cuenta encarece adivinar una contraseña concreta, que es el ataque que
    | importa. Por dirección ataja a quien prueba muchas cuentas desde un mismo
    | lugar, dejando margen para que varias personas de la misma oficina se
    | equivoquen al escribir sin estorbarse.
    |
    | Rige en la ÚNICA puerta de acceso, el formulario del panel, por
    | «Ingresar», que sobrescribe el conteo de fábrica de Filament —cinco por
    | dirección y nada más— porque era justo el que dejaba fuera a la oficina
    | entera. No usa el «throttle:*» de fábrica de Laravel, que cuenta toda
    | petición, aciertos incluidos, y nunca limpia. (Hubo una segunda puerta,
    | «/login», con su propio limitador en «routes/web.php»; se retiró, y con
    | ella la recuperación de contraseña propia, que limitaba solo por IP.)
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Peso máximo de lo que se sube
    |--------------------------------------------------------------------------
    |
    | En kilobytes, y por debajo de «upload_max_filesize» de PHP (ver
    | docker/php-seguridad.ini): si el formulario aceptara más de lo que el
    | servidor recibe, el archivo se perdería en el camino sin explicación.
    |
    | Un campo concreto puede pedir más o menos con ->maxSize() cuando lo
    | necesite; esto es solo lo que rige si nadie dice nada.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Qué tipos de archivo se aceptan
    |--------------------------------------------------------------------------
    |
    | Lista de lo que SE acepta, no de lo que se prohíbe: una lista de
    | prohibidos siempre se queda corta —.phtml, .phar, .pht, .cgi—.
    |
    | Rige en todo campo de subida mientras nadie diga otra cosa; un campo que
    | necesite algo distinto lo declara con ->acceptedFileTypes([...]).
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Ancho máximo de las imágenes que se suben
    |--------------------------------------------------------------------------
    |
    | Las fotos de teléfono llegan con 4000 px de ancho y entre 8 y 12 MB, y se
    | muestran a 800 px como mucho. Se reducen EN EL NAVEGADOR antes de salir:
    | sube menos por la red —que en una municipalidad no sobra—, ocupa menos
    | disco y el servidor no tiene que procesarlas.
    |
    | Lo hace el propio Filament con FilePond, sin ninguna dependencia extra.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Cuántos códigos del segundo factor se pueden pedir
    |--------------------------------------------------------------------------
    |
    | Existe para que quien ya pasó usuario y contraseña no pueda usar la casilla
    | de otra persona como buzón: dejar que el código expire, recargar, y forzar
    | un correo nuevo indefinidamente.
    |
    | El plazo es corto a propósito. Un tope de una hora castiga sobre todo a
    | quien tiene un problema de verdad —no le llega el correo, se equivoca,
    | cierra la pestaña— y lo deja fuera del sistema toda la mañana. Media hora
    | encarece el abuso lo suficiente sin convertir un mal rato en un día
    | perdido.
    |
    | Se apaga en la batería de pruebas: una automatización que entra y sale
    | varias veces agota el tope y se bloquea a sí misma.
    |
    */

    'codigos_por_ventana' => (int) env('SEGURIDAD_CODIGOS_POR_VENTANA', 5),

    'ventana_de_codigos_en_minutos' => (int) env('SEGURIDAD_VENTANA_CODIGOS_MINUTOS', 30),

    'ancho_maximo_de_imagen' => (int) env('SEGURIDAD_ANCHO_MAXIMO_IMAGEN', 2000),

    'tipos_de_archivo_aceptados' => [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/avif',
        'image/gif',
        'application/pdf',
        'text/csv',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ],

    'subida_maxima_kb' => (int) env('SEGURIDAD_SUBIDA_MAXIMA_KB', 20480),

    'intentos_por_cuenta' => (int) env('SEGURIDAD_INTENTOS_POR_CUENTA', 5),

    'intentos_por_origen' => (int) env('SEGURIDAD_INTENTOS_POR_ORIGEN', 20),

    /*
    |--------------------------------------------------------------------------
    | Contraseñas ya filtradas
    |--------------------------------------------------------------------------
    |
    | Al elegir una contraseña se pregunta a Have I Been Pwned si ya apareció en
    | alguna filtración. Sale solo un fragmento del resumen criptográfico
    | (k-anonymity), nunca la contraseña; lo hace App\Support\VerificadorDeFiltraciones.
    |
    | Lo que decide esta configuración es QUÉ PASA CUANDO NO SE PUEDE PREGUNTAR,
    | que en una isla sin salida a internet es siempre. El verificador de fábrica
    | de Laravel se calla y da la contraseña por limpia: la garantía desaparece
    | sin que nadie se entere. Eso es lo que acá se elige a mano.
    |
    |   'cerrado'  Sin respuesta, la contraseña se RECHAZA. La garantía es real o
    |              no hay alta. Cuesta disponibilidad: si el servicio externo se
    |              cae, nadie puede crear una cuenta ni restablecer su
    |              contraseña —el acceso de quien ya la tiene NO se toca, esto
    |              solo rige al ELEGIR una contraseña nueva—.
    |   'abierto'  Sin respuesta, la contraseña se ACEPTA, pero queda una línea
    |              de aviso en el registro. Prioriza que el sistema siga
    |              funcionando; el precio es que la comprobación puede llevar
    |              meses apagada y solo lo sabe quien lea los registros.
    |   'apagado'  No se pregunta nada, y la política de contraseñas tampoco lo
    |              promete: la regla «uncompromised» ni se aplica. Es lo que
    |              corresponde en un servidor sin salida a internet, donde la
    |              comprobación no iba a funcionar nunca.
    |
    | Por defecto va 'cerrado' fuera de local y de la batería de pruebas. El
    | motivo: con 'abierto' por omisión, una isla sin salida degrada a «no se
    | comprueba nunca» y la única señal es una línea de registro que nadie mira
    | —o sea, el mismo problema con un paso más—. Con 'cerrado' el fallo aparece
    | el primer día, es inequívoco, y la salida ('apagado') es una variable que
    | además deja ESCRITO que en ese servidor no hay comprobación de filtraciones.
    |
    | En local y en pruebas va 'apagado': atar el alta de un usuario a la red
    | hace que un rojo no signifique nada y que una automatización que crea
    | usuarios dependa de que haya internet.
    |
    */

    'filtraciones' => [

        'modo' => env(
            'SEGURIDAD_FILTRACIONES',
            in_array(env('APP_ENV', 'production'), ['local', 'testing'], true) ? 'apagado' : 'cerrado',
        ),

        // El de fábrica son 30 segundos. Multiplicado por cada alta y cada
        // cambio de contraseña, en un servidor sin salida es media pantalla
        // colgada por nada: el servicio responde en menos de un segundo cuando
        // se puede llegar a él.
        'espera_segundos' => (int) env('SEGURIDAD_FILTRACIONES_ESPERA', 3),

    ],

];
