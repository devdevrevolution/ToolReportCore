<?php

declare(strict_types=1);

namespace Toolreport\Core;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Toolreport\Core\Console\Commands\GenerateCoreFontsCommand;
use Toolreport\Core\Console\Commands\PdfDesignerInstallCommand;
use Toolreport\Core\Layout\LayoutEngine;
use Toolreport\Core\Layout\Renderers\BarcodeElementRenderer;
use Toolreport\Core\Layout\Renderers\ContainerElementRenderer;
use Toolreport\Core\Layout\Renderers\ImageElementRenderer;
use Toolreport\Core\Layout\Renderers\LineElementRenderer;
use Toolreport\Core\Layout\Renderers\PageNumberElementRenderer;
use Toolreport\Core\Layout\Renderers\RectangleElementRenderer;
use Toolreport\Core\Layout\Renderers\TableElementRenderer;
use Toolreport\Core\Layout\Renderers\TextElementRenderer;
use Toolreport\Core\Pdf\EngineSelector;
use Toolreport\Core\Pdf\PdfGenerator;
use Toolreport\Core\Modules\PdfEngine\Engine\ReportCompiler;

class ToolreportCoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/pdf-designer.php', 'pdf-designer');

        $this->app->singleton(LayoutEngine::class, function () {
            $engine = new LayoutEngine();
            $engine->registerRenderers([
                app(TextElementRenderer::class),
                app(ImageElementRenderer::class),
                app(TableElementRenderer::class),
                app(LineElementRenderer::class),
                app(RectangleElementRenderer::class),
                app(BarcodeElementRenderer::class),
                app(PageNumberElementRenderer::class),
                app(ContainerElementRenderer::class),
            ]);

            return $engine;
        });

        $this->app->singleton(PdfGenerator::class);

        $this->app->singleton(EngineSelector::class);

        // ── PDF Engine (Composite-pattern tc-lib-pdf renderer) ──

        // Force Tcpdf to use cURL for remote image fetching.
        // Without this, File::getUrlData() skips cURL when allow_url_fopen=1
        // and falls back to file_get_contents(), which can't read remote URLs.
        if (! defined('FORCE_CURL')) {
            define('FORCE_CURL', true);
        }

        $this->app->singleton(ReportCompiler::class, function () {
            $debug = (bool) config('pdf-designer.debug', false);
            $fileOptions = config('pdf-designer.pdf-engine.fileOptions', []);
            return new ReportCompiler(debug: $debug, fileOptions: $fileOptions);
        });
    }

    public function boot(): void
    {
        // Config
        $this->publishes([
            __DIR__.'/../config/pdf-designer.php' => config_path('pdf-designer.php'),
        ], 'pdf-designer-config');

        // Migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'pdf-designer-migrations');

        // Routes
        if (file_exists(__DIR__.'/../routes/api.php')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        }

        // ── Designer Frontend ──
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'pdf-designer');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/pdf-designer'),
        ], 'pdf-designer-views');

        // Web route for the designer (only when not running in console)
        if (! $this->app->runningInConsole()) {
            Route::get('/pdf-designer/{templateId?}', function () {
                return view('pdf-designer::pdf-designer');
            })->name('pdf-designer.index');
        }

        // Commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                PdfDesignerInstallCommand::class,
                GenerateCoreFontsCommand::class,
            ]);
        }

        // PDF Engine font path
        $this->configurePdfEngineFontPath();
    }

    private function configurePdfEngineFontPath(): void
    {
        if (defined('K_PATH_FONTS')) {
            return;
        }

        // 1. Try the bundled core fonts (shipped with this package)
        // Use dirname() once to go from src/ to the package root, avoiding
        // double-dots ('..') in the path — tc-lib-file's security check
        // rejects paths containing '..' (hasDoubleDots).
        $bundledCorePath = dirname(__DIR__).'/fonts/core';
        if (is_dir($bundledCorePath)) {
            define('K_PATH_FONTS', $bundledCorePath);
            return;
        }

        // 2. Try the data-core package (installed via composer or build)
        $dataCorePath = base_path('vendor/tecnickcom/tc-lib-pdf-font/target/fonts/core');
        if (is_dir($dataCorePath)) {
            define('K_PATH_FONTS', $dataCorePath);
            return;
        }

        // 3. Fallback: look in the parent target/fonts directory
        $vendorFontPath = base_path('vendor/tecnickcom/tc-lib-pdf-font/target/fonts');
        if (is_dir($vendorFontPath)) {
            define('K_PATH_FONTS', $vendorFontPath);
        }
    }
}
