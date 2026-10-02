<?php

use App\Support\CredencialesDePlantilla;

/**
 * Lo que no puede terminar dentro de una imagen que se publica.
 *
 * Sin «.dockerignore», el «COPY . .» del Dockerfile se lleva a la imagen el
 * .env con las credenciales de producción, el historial de git entero y
 * node_modules. Una imagen publicada en un registro es un archivo que cualquiera
 * con acceso puede abrir capa por capa.
 */
it('la imagen no se lleva credenciales ni historial', function () {
    $archivo = base_path('.dockerignore');

    expect(file_exists($archivo))->toBeTrue('Falta .dockerignore: la imagen se lleva el .env');

    $reglas = (string) file_get_contents($archivo);

    expect($reglas)
        ->toContain('.env')
        ->toContain('.git')
        ->toContain('node_modules')
        // El ejemplo sí tiene que viajar: es lo que documenta qué variables
        // espera el sistema, y no lleva ningún secreto.
        ->toContain('!.env.example');
});

/**
 * Artefactos locales que existen en el checkout de quien desarrolla y no pueden
 * salir de su equipo: el token de los paquetes privados y las cookies de sesión
 * que Playwright deja tras iniciar sesión de verdad.
 *
 * Están todos en el .gitignore, así que nunca llegan al repositorio; el agujero
 * son las copias que no pasan por git: el «COPY . .» del Dockerfile (acá) y la
 * copia del generador (tests/Generador/ElGeneradorNoViajaNiContaminaTest).
 *
 * @return list<string>
 */
function artefactosQueNoSalenDelEquipo(): array
{
    return ['auth.json', 'e2e/.auth', '.superpowers', 'test-results', '.phpunit.result.cache'];
}

it('la imagen no se lleva el token de los paquetes privados ni sesiones de prueba', function () {
    // «auth.json» guarda el token fine-grained de los repositorios privados y
    // vive en la raíz del proyecto: un «docker build» hecho desde el equipo de
    // quien desarrolla —no desde el CI, que no lo tiene— lo hornea en la imagen,
    // y una imagen publicada la abre capa por capa cualquiera con acceso al
    // registro. Lo mismo con «e2e/.auth», que son sesiones ya autenticadas.
    $reglas = (string) file_get_contents(base_path('.dockerignore'));

    // str_contains y no toContain(): toContain es variádico —cada argumento es
    // otra aguja que buscar—, así que un mensaje pasado como segundo argumento
    // se convierte en una condición extra que nunca se cumple.
    foreach (artefactosQueNoSalenDelEquipo() as $artefacto) {
        expect(str_contains($reglas, $artefacto))->toBeTrue(
            "El .dockerignore no excluye «{$artefacto}»: el COPY del Dockerfile lo mete en la imagen",
        );
    }
});

it('el candado del despliegue también busca el token de composer', function () {
    // El paso que inspecciona la imagen antes de publicarla solo miraba .env y
    // .env.host: un auth.json horneado pasaba el control y se publicaba.
    $contenido = (string) file_get_contents(base_path('.github/workflows/desplegar.yml'));

    expect(str_contains($contenido, '/app/auth.json'))->toBeTrue(
        'La comprobación de la imagen no busca /app/auth.json',
    );
});

it('el README dice las versiones que el repo tiene de verdad', function () {
    // Anunciaba «Laravel 12 + Filament 3» con el repo en Laravel 13 + Filament 5:
    // quien genera un proyecto se lleva la documentación de otra versión y busca
    // APIs que ya no existen.
    $readme = (string) file_get_contents(base_path('README.md'));

    expect($readme)->not->toContain('Filament 3')
        ->and($readme)->not->toContain('Laravel 12')
        // «PWA» era un resto: el scaffold no trae manifest.json ni service
        // worker desde hace rato, y el título seguía prometiéndolo.
        ->and($readme)->not->toContain('PWA');
});

it('el .env de ejemplo no trae ninguna clave de aplicación', function () {
    // La clave de la aplicación cifra sesiones y datos: si alguna vez alguien
    // pega una real acá, viaja al repositorio y descifra todo lo de ese sistema.
    $ejemplo = (string) file_get_contents(base_path('.env.example'));

    expect($ejemplo)->not->toContain('APP_KEY=base64:');
});

it('la contraseña de plantilla no puede llegar a producción', function () {
    // «sistema_pass» está en el .env.example a propósito: es la contraseña del
    // contenedor de desarrollo y hace que un proyecto recién generado levante
    // sin configurar nada. El problema es el día que alguien despliega sin
    // cambiarla: el scaffold es público, así que esa contraseña la conoce
    // cualquiera. Arrancar en producción con ella tiene que fallar, y fallar
    // diciendo exactamente qué hay que cambiar.
    // detectEnvironment y no config('app.env'): environment() lee el entorno
    // resuelto de la aplicación, no la clave de configuración.
    $this->app->detectEnvironment(fn (): string => 'production');
    config()->set('database.connections.'.config('database.default').'.password', 'sistema_pass');

    expect(fn () => CredencialesDePlantilla::comprobar())
        ->toThrow(RuntimeException::class);
});

it('en desarrollo la contraseña de plantilla no molesta', function () {
    // Si esto fallara, un proyecto recién generado no levantaría y la salvaguarda
    // sería peor que el problema que evita.
    $this->app->detectEnvironment(fn (): string => 'local');
    config()->set('database.connections.'.config('database.default').'.password', 'sistema_pass');

    CredencialesDePlantilla::comprobar();

    expect(true)->toBeTrue();
});

// El enganche al arranque (AppServiceProvider::boot() la llama como primera línea)
// lo vigila CandadosDelEcosistemaTest, bloque «guardaDeCredencialesDePlantilla»,
// ejecutando boot() de verdad con una credencial de plantilla.

it('la contraseña de plantilla de los respaldos no puede llegar a producción', function () {
    // Los respaldos son la base completa con PII, descargables desde el panel,
    // y van cifrados con esta contraseña. «cambiame-por-algo-seguro-en-producción»
    // estuvo publicada en el repositorio como valor por omisión del
    // «.env.example»: aunque ya se dejó vacía ahí (ver
    // «el .env de ejemplo ya no trae una contraseña de respaldos de plantilla»,
    // más abajo), el valor sigue publicado en el historial de git y en
    // cualquier «.env» copiado de una versión vieja del ejemplo. Si se filtra
    // un respaldo cifrado con ella, se abre con la clave que cualquiera puede
    // buscar en este repositorio.
    $this->app->detectEnvironment(fn (): string => 'production');
    // La de la base, en regla: si no, el candado salta por ella y esta prueba no
    // mediría la de los respaldos.
    config()->set('database.connections.'.config('database.default').'.password', 'una-de-verdad');
    config()->set('backup.backup.password', 'cambiame-por-algo-seguro-en-produccion');

    expect(fn () => CredencialesDePlantilla::comprobar())
        ->toThrow(RuntimeException::class, 'BACKUP_ARCHIVE_PASSWORD');
});

it('en desarrollo la contraseña de plantilla de los respaldos no molesta', function () {
    $this->app->detectEnvironment(fn (): string => 'local');
    config()->set('backup.backup.password', 'cambiame-por-algo-seguro-en-produccion');

    CredencialesDePlantilla::comprobar();

    expect(true)->toBeTrue();
});

it('el .env de ejemplo ya no trae una contraseña de respaldos de plantilla', function () {
    // Publicar UN VALOR CONCRETO -aunque el candado de arriba lo detecte en
    // producción- deja el mismo problema para quien despliega en desarrollo o
    // en un ambiente que no pasa por ese candado (un script que arma el .env
    // sin pasar por «app()->environment()», por ejemplo): cualquiera que haya
    // visto este repositorio conoce la clave con la que se cifran los
    // respaldos. Se deja vacía a propósito, con un comentario que dice que hay
    // que generarla -nunca una contraseña inventada, que solo cambiaría CUÁL
    // valor publicado circula-.
    $ejemplo = (string) file_get_contents(base_path('.env.example'));

    expect($ejemplo)->toContain('BACKUP_ARCHIVE_PASSWORD=')
        ->and($ejemplo)->not->toContain('BACKUP_ARCHIVE_PASSWORD=cambiame-por-algo-seguro-en-produccion')
        ->and(preg_match('/^BACKUP_ARCHIVE_PASSWORD=.+$/m', $ejemplo))->toBe(
            0,
            'BACKUP_ARCHIVE_PASSWORD sigue con un valor en el .env.example: debe quedar vacía',
        );
});

it('el candado no tumba el build de la imagen', function () {
    // Durante «composer install» corre «package:discover», que arranca la
    // aplicación SIN .env: ahí APP_ENV no está definida y Laravel asume
    // «production». Un candado que lance excepción por cualquier motivo en ese
    // momento deja la imagen sin construirse, y el error que se ve no menciona
    // el candado por ninguna parte. Ya pasó una vez en el ecosistema.
    //
    // Por eso solo salta con la contraseña de plantilla EXACTA: sin
    // configuración de base de datos —que es como arranca el build— no hay nada
    // que comparar y no lanza.
    $this->app->detectEnvironment(fn (): string => 'production');
    config()->set('database.connections.'.config('database.default').'.password', '');
    config()->set('backup.backup.password', '');
    // Sin .env, config/mfa.php cae en sus valores por omisión: encendido y sin
    // mostrar el código. Se fijan así para no depender del .env de quien prueba.
    config()->set('mfa.enabled', true);
    config()->set('mfa.show_code', false);
    config()->set('waitlist.hash_salt', str_repeat('s', 32));
    config()->set('services.turnstile.sitekey', '0x4AAAAAAAreal');
    config()->set('services.turnstile.secret', '0x4AAAAAAAsecretoreal');

    CredencialesDePlantilla::comprobar();

    expect(true)->toBeTrue('El candado saltó sin credenciales configuradas y eso rompe el build');
});

it('el .env de ejemplo declara las variables que config/ necesita para tener efecto', function () {
    // Cada una de estas se lee con env() en algún config/*.php de este repo.
    // Faltando del .env.example, quien genera un proyecto nuevo no sabe que
    // existen y el valor por defecto queda fijo sin que nadie lo note.
    $ejemplo = (string) file_get_contents(base_path('.env.example'));

    $variables = [
        'TRUSTED_PROXIES',       // config/proxies.php
        'ASSET_URL',             // config/filesystems.php
        'MAIL_SCHEME',           // config/mail.php (reemplaza a MAIL_ENCRYPTION, que ya no se lee)
        'MAIL_EHLO_DOMAIN',      // config/mail.php
        'AWS_ACCESS_KEY_ID',     // config/filesystems.php (disco s3, opcional)
        'AWS_SECRET_ACCESS_KEY',
        'AWS_DEFAULT_REGION',
        'AWS_BUCKET',
        'AWS_URL',
        'AWS_ENDPOINT',
        'AWS_USE_PATH_STYLE_ENDPOINT',
        'SEGURIDAD_CODIGOS_POR_VENTANA',   // config/seguridad.php
        'SEGURIDAD_VENTANA_CODIGOS_MINUTOS',
        'SEGURIDAD_ANCHO_MAXIMO_IMAGEN',
        'SEGURIDAD_SUBIDA_MAXIMA_KB',
        'SEGURIDAD_INTENTOS_POR_CUENTA',
        'SEGURIDAD_INTENTOS_POR_ORIGEN',
    ];

    // str_contains y no toContain(): toContain es variádico —cada argumento es
    // otra aguja que buscar—, así que un mensaje pasado como segundo argumento
    // se convierte en una condición extra que nunca se cumple.
    foreach ($variables as $variable) {
        expect(str_contains($ejemplo, "{$variable}="))->toBeTrue("Falta {$variable} en .env.example");
    }

    // config/mail.php usa 'scheme', no 'encryption': MAIL_ENCRYPTION=... como
    // variable propia del .env.example no llega a ningún lado (solo puede
    // quedar mencionada en un comentario, que es lo que hace la de arriba).
    expect(str_contains($ejemplo, 'MAIL_ENCRYPTION='))->toBeFalse();
});

it('desplegar.yml exporta APP_IMAGE antes de cualquier uso de $COMPOSE', function () {
    // C1: docker-compose.prod.yml exige APP_IMAGE en «app» Y en «worker» con
    // `${APP_IMAGE:?...}», y Compose interpola el archivo COMPLETO en
    // CUALQUIER subcomando —no solo en «up»—. Sin exportarla ANTES de la
    // primera llamada a $COMPOSE, el «ps» que decide si hay que respaldar
    // fallaba dentro de un `if ... >/dev/null 2>&1` que se tragaba el error
    // —el respaldo se saltaba en silencio, como si no hubiera contenedor
    // previo— y el «migrate --force» posterior abortaba por «set -e» dejando
    // contenedores nuevos con el esquema viejo.
    $contenido = (string) file_get_contents(base_path('.github/workflows/desplegar.yml'));

    $inicioBloque = strpos($contenido, "run: |\n          set -euo pipefail");
    expect($inicioBloque)->not->toBeFalse('No se encontró el bloque `run` del job «desplegar»');

    $appImageExportada = false;

    foreach (explode("\n", substr($contenido, $inicioBloque)) as $numero => $linea) {
        $sinEspacios = ltrim($linea);

        // Los comentarios explicando el porqué (como este mismo test)
        // mencionan «$COMPOSE» en prosa: no son código, no cuentan.
        if (str_starts_with($sinEspacios, '#')) {
            continue;
        }

        if (str_contains($linea, 'export APP_IMAGE=')) {
            $appImageExportada = true;

            continue;
        }

        if (str_contains($linea, '$COMPOSE')) {
            expect($appImageExportada)->toBeTrue(
                "Línea {$numero} del bloque «desplegar» usa \$COMPOSE antes de exportar APP_IMAGE: «".trim($linea).'»',
            );
        }
    }
});

it('los respaldos avisan a la dirección configurada y se guardan en el disco configurado', function () {
    // config/backup.php traía 'to' => 'your@example.com' y 'disks' => ['local']
    // escritos a mano: un respaldo fallido avisaba a una dirección ficticia, y
    // cambiar de disco vía BACKUP_DISKS no movía dónde monitorear la copia.
    $config = (string) file_get_contents(config_path('backup.php'));

    expect($config)->toContain("env('BACKUP_NOTIFICATION_EMAIL'")
        ->and($config)->toContain("env('BACKUP_DISKS'")
        ->and($config)->not->toContain("'to' => 'your@example.com'");
});

it('la imagen base de FrankenPHP está fijada a una versión concreta', function () {
    // `dunglas/frankenphp:1-php8.5-alpine` es una etiqueta que se MUEVE: el
    // mantenedor la reapunta cada vez que publica una versión menor. Dos
    // construcciones del mismo commit dan entonces imágenes distintas, y basta
    // un rebuild del CI —una dependencia actualizada, un reintento— para que el
    // servidor que atiende a los vecinos se lleve un FrankenPHP que nadie
    // eligió y que ningún commit explica. Si esa versión trae una regresión,
    // tampoco hay a qué volver: la etiqueta vieja ya apunta a otra imagen.
    //
    // Se sube a propósito, cambiando el número en el Dockerfile, en su propio
    // commit y construyendo la imagen para probarla.
    preg_match_all(
        '/^FROM\s+dunglas\/frankenphp:(\S+)/mi',
        (string) file_get_contents(base_path('Dockerfile')),
        $etapas
    );

    expect($etapas[1])->not->toBeEmpty('el Dockerfile ya no parte de una imagen de FrankenPHP');

    foreach ($etapas[1] as $etiqueta) {
        expect($etiqueta)->toMatch(
            '/^\d+\.\d+\.\d+-php\d+\.\d+/',
            "«{$etiqueta}» es una etiqueta flotante: fija la versión completa (p. ej. 1.12.7-php8.5-alpine)"
        );
    }
});

it('la imagen sabe decir si está sana, y lo dice con una shell', function () {
    // La imagen base NO viene sin chequeo, y ahí está la trampa:
    // `dunglas/frankenphp` declara el suyo contra `localhost:2019/metrics`, la
    // API admin de Caddy. Ese puerto lo abre Caddy y devuelve 200 aunque Octane
    // haya dejado de atender en el 8000, así que el contenedor se declaraba sano
    // con la aplicación caída: el despliegue lo aceptaba y el reinicio
    // automático nunca se disparaba. Por eso el Dockerfile tiene que
    // SOBREESCRIBIR el chequeo heredado; borrarlo no deja la imagen sin chequeo,
    // la deja con el que miente en verde.
    //
    // Une las continuaciones de línea («\» al final) para leer la instrucción
    // completa aunque esté partida en varias por legibilidad.
    $dockerfile = (string) preg_replace(
        '/\\\\\s*\n\s*/', ' ', (string) file_get_contents(base_path('Dockerfile'))
    );

    $instruccion = [];
    preg_match('/^HEALTHCHECK[^\n]*/mi', $dockerfile, $instruccion);

    expect($instruccion)->not->toBeEmpty('la imagen de producción no declara HEALTHCHECK');

    // Forma exec (`CMD ["curl", …]`): ahí NO hay shell, así que el `|| exit 1`
    // viajaría como dos argumentos más de curl en vez de decidir el resultado,
    // y el contenedor quedaría sano pasara lo que pasara.
    expect($instruccion[0])->not->toMatch(
        '/CMD\s*\[/',
        'el HEALTHCHECK usa la forma exec: sin shell, el «|| exit 1» no hace nada'
    );

    // Y que no sea el chequeo heredado: `localhost:2019/metrics` responde 200
    // mientras Caddy esté vivo, aunque Octane no atienda ni una petición.
    expect($instruccion[0])->not->toMatch(
        '/:2019/',
        'el HEALTHCHECK volvió a comprobar la API admin de Caddy: responde 200 aunque Octane no atienda'
    );

    // Contra la ruta de salud que declara bootstrap/app.php, que responde sin
    // tocar la base ni la cola: un corte momentáneo de MariaDB no debe hacer que
    // Docker mate y reinicie la aplicación en bucle.
    expect($instruccion[0])->toMatch(
        '#/up#',
        'el HEALTHCHECK no apunta a la ruta de salud declarada en bootstrap/app.php'
    );

    // El chequeo se hace con curl. La base de FrankenPHP hoy lo trae —su propio
    // chequeo heredado lo usaba—, pero se declara igual en el `apk add` para que
    // el chequeo no dependa de eso: si una versión futura de la base dejara de
    // incluirlo, el chequeo fallaría siempre y el contenedor quedaría
    // «unhealthy» con la aplicación sana, que es peor que no tener chequeo
    // porque enseña a ignorarlo.
    expect($dockerfile)->toMatch(
        '/apk add[^\n]*\bcurl\b/',
        'el HEALTHCHECK usa curl y la imagen no lo instala'
    );
});
