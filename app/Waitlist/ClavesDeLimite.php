<?php

declare(strict_types=1);

namespace App\Waitlist;

/**
 * Componentes normalizados de las claves de los límites de la lista, para que
 * escribir lo mismo de otra forma no abra una cubeta nueva.
 *
 * - `dominio()`: minúsculas, sin punto final, IDN en punycode y reducido al
 *   dominio registrable (los subdominios cuentan como el dominio). Sin librería
 *   de Public Suffix (no se suman dependencias): últimas dos etiquetas, o tres
 *   si la penúltima es un sufijo de segundo nivel común bajo un ccTLD
 *   (`co.uk`, `gob.cl`, `com.ar`…). Puede juntar de más en un sufijo raro, que
 *   para un límite es el lado seguro.
 * - `buzon()`: el buzón que de verdad recibe el correo, para el enfriamiento de
 *   24 h por correo: sin `+etiqueta` (todos los grandes proveedores la
 *   entregan en el mismo buzón) y, en Gmail, sin puntos y con googlemail.com
 *   como gmail.com. Solo para límites: la inscripción guarda la dirección tal
 *   como la escribió la persona.
 *
 * Todo se recorta antes de procesarlo (el campo lo escribe el cliente) y quien
 * arma la clave la hashea, así que su largo no depende de la entrada.
 */
final class ClavesDeLimite
{
    private const int LARGO_MAXIMO = 320;

    /** Segundos niveles que bajo un ccTLD de dos letras son sufijo público. */
    private const array SEGUNDOS_NIVELES = ['ac', 'co', 'com', 'edu', 'gob', 'gov', 'mil', 'net', 'nic', 'org'];

    private const array GMAIL = ['gmail.com', 'googlemail.com'];

    public static function dominio(mixed $email): string
    {
        [, $dominio] = self::partes($email);

        $etiquetas = explode('.', $dominio);
        $cuantas = count($etiquetas);

        if ($cuantas <= 2) {
            return $dominio;
        }

        $tld = $etiquetas[$cuantas - 1];
        $segundo = $etiquetas[$cuantas - 2];
        $tomar = strlen($tld) === 2 && in_array($segundo, self::SEGUNDOS_NIVELES, true) ? 3 : 2;

        return implode('.', array_slice($etiquetas, -$tomar));
    }

    public static function buzon(mixed $email): string
    {
        [$local, $dominio] = self::partes($email);

        $local = explode('+', $local, 2)[0];

        if (in_array($dominio, self::GMAIL, true)) {
            $local = str_replace('.', '', $local);
            $dominio = 'gmail.com';
        }

        return $local.'@'.$dominio;
    }

    /** @return array{0: string, 1: string} */
    private static function partes(mixed $email): array
    {
        if (! is_string($email)) {
            return ['', ''];
        }

        $email = trim(substr($email, 0, self::LARGO_MAXIMO));
        $arroba = strrpos($email, '@');

        if ($arroba === false) {
            return [mb_strtolower($email), ''];
        }

        $local = mb_strtolower(substr($email, 0, $arroba));
        $dominio = rtrim(mb_strtolower(substr($email, $arroba + 1)), '.');

        if ($dominio !== '' && function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($dominio, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            $dominio = is_string($ascii) ? strtolower($ascii) : $dominio;
        }

        return [$local, $dominio];
    }
}
