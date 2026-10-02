<?php

declare(strict_types=1);

namespace App\Support\Sitio;

/** Una entrada del sitemap. `loc` es una ruta relativa; el sitemap la vuelve absoluta con app.url. */
final readonly class Url
{
    public function __construct(
        public string $loc,
        public ?string $lastmod = null,
        public ?string $changefreq = null,
        public ?float $priority = null,
    ) {}
}
