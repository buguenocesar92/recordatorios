<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Filament\Widgets\Widget;

/**
 * Un widget del panel al que se le olvidó el trait de Shield.
 *
 * Mismo motivo que la página: la regla tiene que demostrar que rechaza algo.
 */
class WidgetSinEscudo extends Widget
{
    protected string $view = 'filament.widgets.inexistente';
}
