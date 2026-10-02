<?php

/**
 * Parser del árbol ASCII «Estructura del Scaffold» del README.
 *
 * FUENTE ÚNICA: hasta antes de este archivo, la misma expresión regular vivía
 * copiada a mano en tres lugares (`tools/usar-paquete-compartido.php`,
 * `tests/Feature/EstructuraDelReadmeTest.php` y
 * `tests/Generador/ElPaqueteCompartidoPodaElReadmeTest.php`) con un comentario
 * en cada uno prometiendo que era "la MISMA expresión" que las otras —una
 * convención documentada, no garantizada en tiempo de ejecución. Si el
 * formato del árbol cambia (otro ancho de sangría, otro conector) y se
 * actualiza el regex en un solo lugar, las copias divergen en silencio: el
 * README promete algo que el disco ya no tiene, o el aplicador no encuentra
 * lo que sí debería podar. Este archivo se `require`-ea desde los tres.
 *
 * Viaja al proyecto generado: `tools/` NO está en la lista de exclusión de
 * `scaffold` (ver ese script), así que `EstructuraDelReadmeTest`, que también
 * viaja, lo sigue teniendo disponible en la misma ruta relativa.
 */

/** El ancla del bloque de árbol en el README: el mismo bloque que pinta `scaffold-laravel-filament-pwa/`. */
const PATRON_BLOQUE_ARBOL_DEL_README = '/```\nscaffold-laravel-filament-pwa\/\n(.*?)```/us';

if (! function_exists('nodoDelArbol')) {
    /**
     * Parsea una línea del árbol ASCII: cada nivel ocupa 4 columnas («│   » o
     * «    »), luego «├── » o «└── », el nombre y, opcionalmente, un
     * comentario. Devuelve null para una línea que no es del árbol.
     *
     * @return array{profundidad: int, nombre: string, resto: string}|null
     */
    function nodoDelArbol(string $linea): ?array
    {
        if (preg_match('/^((?:│   |    )*)(?:├── |└── )([^\s#]+)(.*)$/u', $linea, $partes) !== 1) {
            return null;
        }

        return [
            'profundidad' => (int) (mb_strlen($partes[1]) / 4),
            'nombre' => $partes[2],
            'resto' => $partes[3],
        ];
    }
}

if (! function_exists('hojasDelArbol')) {
    /**
     * Rutas de las hojas del árbol: lo que `EstructuraDelReadmeTest` comprueba
     * que exista de verdad en disco.
     *
     * @param  list<string>  $lineas
     * @return list<string>
     */
    function hojasDelArbol(array $lineas): array
    {
        $pila = [];
        $hojas = [];

        foreach ($lineas as $linea) {
            $nodo = nodoDelArbol($linea);
            if ($nodo === null) {
                continue;
            }

            $pila = array_slice($pila, 0, $nodo['profundidad']);
            $pila[] = $nodo['nombre'];

            if (! str_ends_with($nodo['nombre'], '/') && str_contains($nodo['nombre'], '.')) {
                $hojas[] = implode('', $pila);
            }
        }

        return $hojas;
    }
}

if (! function_exists('hojasDelArbolDesdeReadme')) {
    /**
     * Extrae el bloque «Estructura del Scaffold» del contenido de un README y
     * devuelve las hojas de su árbol. Lanza si el bloque no aparece: quien
     * llama decide cómo reportar el fallo. `tools/usar-paquete-compartido.php`
     * no usa esta función porque necesita además el offset del bloque (para
     * reescribirlo en el mismo lugar) y su propio mensaje de error con
     * `morir()`; hace su propia extracción con `PATRON_BLOQUE_ARBOL_DEL_README`.
     *
     * @return list<string>
     */
    function hojasDelArbolDesdeReadme(string $contenidoReadme): array
    {
        if (preg_match(PATRON_BLOQUE_ARBOL_DEL_README, $contenidoReadme, $bloque) !== 1) {
            throw new RuntimeException('No encontré el bloque «Estructura del Scaffold» en el README.');
        }

        return hojasDelArbol(explode("\n", trim($bloque[1], "\n")));
    }
}
