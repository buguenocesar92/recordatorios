<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Los tests que renderizan páginas completas pasan por los layouts con
        // @vite. En CI no se compilan los assets (no hay manifest), así que se
        // stubea Vite para que @vite no lance ViteManifestNotFoundException.
        $this->withoutVite();
    }
}
