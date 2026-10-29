<?php

declare(strict_types=1);

/**
 * «memory_limit = -1» dejaba que una sola petición se comiera todo el
 * «mem_limit» del contenedor y tumbara a los demás workers de Octane (DoS). El
 * tope por petición debe ser finito y caber en «mem_limit / workers».
 */
function memoryLimitDelHardening(): string
{
    $ini = (string) file_get_contents(base_path('docker/99-php2shell-hardening.ini'));
    preg_match('/^memory_limit\s*=\s*(\S+)\s*$/m', $ini, $m);

    return $m[1] ?? '';
}

it('el hardening fija un memory_limit finito, no -1', function () {
    $valor = memoryLimitDelHardening();

    expect($valor)->not->toBe('')->not->toBe('-1')->toMatch('/^\d+[KMG]?$/');
});

it('el memory_limit por petición cabe en mem_limit dividido entre los workers', function () {
    $unidades = ['K' => 1024, 'M' => 1024 ** 2, 'G' => 1024 ** 3];
    $aBytes = function (string $v) use ($unidades): int {
        $u = strtoupper(substr($v, -1));

        return isset($unidades[$u]) ? (int) $v * $unidades[$u] : (int) $v;
    };

    preg_match('/^\s*mem_limit:\s*(\S+)/m', (string) file_get_contents(base_path('docker-compose.yml')), $c);
    $memLimit = $aBytes(str_replace(['b'], '', strtolower($c[1])));
    preg_match('/OCTANE_WORKERS:-(\d+)/', (string) file_get_contents(base_path('docker/docker-entrypoint.prod.sh')), $w);

    expect($aBytes(memoryLimitDelHardening()))->toBeLessThanOrEqual(intdiv($memLimit, (int) $w[1]));
});
