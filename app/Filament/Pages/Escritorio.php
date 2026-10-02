<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Pages\Dashboard;

/**
 * El escritorio del panel.
 *
 * Reemplaza al de fábrica por la grilla. El de Filament trae dos columnas
 * fijas, y con dos columnas todo widget que valga la pena termina ocupando el
 * ancho completo: el escritorio se convierte en una pila de cinco pantallas.
 * Con seis —divisible por dos y por tres— cada tarjeta declara cuánto ocupa.
 *
 * No lleva «HasPageShield» y debe quedar excluido en
 * «config/filament-shield.php», como lo estaba el escritorio de fábrica: es la
 * primera pantalla del panel, y gatearla con un permiso deja a quien no lo
 * tenga sin ningún sitio al que entrar después de identificarse.
 */
class Escritorio extends Dashboard
{
    /** @return int|array<string, int|null> */
    public function getColumns(): int|array
    {
        return ['default' => 1, 'md' => 2, 'xl' => 6];
    }
}
