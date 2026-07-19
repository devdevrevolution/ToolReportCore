<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Toolreport\Core\ToolreportCoreServiceProvider;

class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate')->run();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ToolreportCoreServiceProvider::class,
            \Barryvdh\DomPDF\ServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function getPackageAliases($app): array
    {
        return [
            'PdfDesigner' => \Toolreport\Core\Facades\PdfDesigner::class,
        ];
    }
}
