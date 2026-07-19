<?php

declare(strict_types=1);

namespace Toolreport\Core\Console\Commands;

use Illuminate\Console\Command;

class PdfDesignerInstallCommand extends Command
{
    protected $signature = 'pdf-designer:install
        {--force : Overwrite existing files}
        {--with-assets : Publish Vue designer assets if available}';

    protected $description = 'Install the PDF Designer package (config, migrations, assets)';

    public function handle(): int
    {
        $this->info('Installing PDF Designer...');

        // Publish config
        $this->call('vendor:publish', [
            '--tag' => 'pdf-designer-config',
            '--force' => $this->option('force'),
        ]);
        $this->info('✓ Config published');

        // Run migrations
        $this->call('migrate');
        $this->info('✓ Migrations executed');

        // Publish designer assets if flag is set
        if ($this->option('with-assets')) {
            $this->call('vendor:publish', [
                '--tag' => 'pdf-designer-assets',
                '--force' => $this->option('force'),
            ]);
            $this->info('✓ Designer assets published');
        }

        $this->info('PDF Designer installed successfully!');
        $this->warn('Add your API routes: ensure Toolreport\Core\ToolreportCoreServiceProvider is registered.');

        return self::SUCCESS;
    }
}
