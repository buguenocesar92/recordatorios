<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Filament\Pages\Page;

/**
 * Una página del panel a la que se le olvidó el trait de Shield.
 *
 * Existe para que la regla de arquitectura tenga algo que rechazar: sin esto,
 * la comprobación pasa en verde solo porque el panel todavía no tiene páginas
 * propias, y el día que alguien agregue una sin trait nadie se enteraría.
 */
class PaginaSinEscudo extends Page
{
    protected string $view = 'filament.pages.inexistente';
}
