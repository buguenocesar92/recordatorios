<?php

/**
 * Las herramientas con las que se trabaja este proyecto.
 *
 * Un proyecto nuevo tiene que nacer sabiendo consultarse a sí mismo: leer su
 * base de datos real en vez de adivinar el esquema, buscar en la documentación
 * de la versión exacta instalada, y abrir un navegador para comprobar lo que
 * dibuja. Si eso no viene configurado, cada quien lo improvisa distinto.
 */
it('las herramientas del proyecto están declaradas y el archivo es válido', function () {
    $ruta = base_path('.mcp.json');

    expect(file_exists($ruta))->toBeTrue('Falta .mcp.json: el proyecto nace sin herramientas');

    $config = json_decode((string) file_get_contents($ruta), true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE, 'El .mcp.json no es JSON válido')
        ->and($config)->toHaveKey('mcpServers');

    /*
     * Las cuatro que las reglas de trabajo dan por obligatorias. Van en el repo
     * y no en la configuración personal porque un sistema nacido de este
     * scaffold tiene que funcionar igual en otro equipo —otro municipio, otra
     * persona— donde no exista el ~/.claude de nadie.
     */
    $obligatorias = [
        'laravel-boost' => 'leer la base real y la documentación de la versión instalada',
        'context7' => 'documentación al día de Laravel, Filament, Livewire y Tailwind',
        'playwright' => 'pruebas de extremo a extremo y regresión visual',
        'chrome-devtools' => 'comprobar la interfaz y medir rendimiento en un navegador de verdad',
    ];

    foreach ($obligatorias as $herramienta => $paraQue) {
        expect(array_key_exists($herramienta, $config['mcpServers']))
            ->toBeTrue("Falta «{$herramienta}» en .mcp.json, que sirve para {$paraQue}");
    }
});

it('ninguna herramienta lleva credenciales escritas en el repositorio', function () {
    // Un .mcp.json se versiona y se publica con el proyecto. Una clave acá no
    // se filtra «si algo sale mal»: se filtra siempre, a todo el que clone.
    //
    // Lo que se prohíbe es el VALOR, no la palabra: buscar «token» como
    // subcadena marcaba «GITHUB_PERSONAL_ACCESS_TOKEN», que es precisamente el
    // nombre de la variable de entorno con la que se hace bien. Un test que
    // grita ante la forma correcta termina desactivado, y con él la vigilancia.
    // Así que se recorren los valores y se exige que cada dato sensible sea una
    // referencia sin resolver —«${VARIABLE}»— y no la credencial en claro.
    $config = json_decode((string) file_get_contents(base_path('.mcp.json')), true);

    $sospechosos = ['api_key', 'apikey', 'token', 'secret', 'password', 'authorization'];

    $recorrer = function (array $nodo, string $ruta) use (&$recorrer, $sospechosos): void {
        foreach ($nodo as $clave => $valor) {
            $donde = $ruta === '' ? (string) $clave : "{$ruta}.{$clave}";

            if (is_array($valor)) {
                $recorrer($valor, $donde);

                continue;
            }

            if (! is_string($valor)) {
                continue;
            }

            $claveEsSensible = (bool) preg_match(
                '/'.implode('|', array_map('preg_quote', $sospechosos)).'/i',
                (string) $clave
            );

            if ($claveEsSensible) {
                expect($valor)->toMatch('/^\$\{[A-Z0-9_]+\}$/', <<<TXT
                    «{$donde}» trae un valor literal en .mcp.json. Las credenciales
                    van por entorno: el valor debe ser una referencia «\${VARIABLE}».
                    TXT);
            }

            // Y una credencial puede colarse en un valor cuya clave no delata nada
            // —un «--header=Bearer eyJ…» dentro de args—, así que también se mira
            // la pinta del propio valor.
            expect($valor)->not->toMatch('/\b(Bearer\s+\S|sk-[A-Za-z0-9]|gh[pousr]_[A-Za-z0-9])/i', <<<TXT
                «{$donde}» parece contener una credencial en claro dentro de .mcp.json.
                TXT);
        }
    };

    $recorrer($config, '');
});

it('boost se ejecuta DENTRO del contenedor, no en el equipo de quien trabaja', function () {
    // Comprobado: en el equipo hay PHP y «artisan» arranca, pero NO alcanza la
    // base de datos —vive en el contenedor y su nombre de red no resuelve
    // fuera—. Con la configuración ingenua, boost levanta igual y sus
    // herramientas más útiles, las que leen la base, fallan sin explicar por
    // qué. Es de las peores formas de fallar: parece que funciona.
    $config = json_decode((string) file_get_contents(base_path('.mcp.json')), true);

    $boost = $config['mcpServers']['laravel-boost'];
    $orden = $boost['command'].' '.implode(' ', $boost['args'] ?? []);

    expect($orden)->toContain('docker')
        ->and($orden)->toContain('exec')
        ->and($orden)->toContain('boost:mcp');
});
