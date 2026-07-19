<?php

declare(strict_types=1);

namespace Toolreport\Core\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;
use ZipArchive;

/**
 * artisan pdf-designer:generate-fonts
 *
 * Downloads Core14 AFM files from Adobe and converts them to the JSON
 * format required by tc-lib-pdf-font. Output goes to fonts/core/.
 */
class GenerateCoreFontsCommand extends Command
{
    protected $signature = 'pdf-designer:generate-fonts
        {--force : Overwrite existing font files}
        {--output= : Output directory (default: package fonts/core/)}';

    protected $description = 'Generate Core14 font JSON files from Adobe AFM sources';

    private const AFM_URL = 'https://partners.adobe.com/public/developer/en/pdf/Core14_AFMs.zip';

    /** Core14 font names => AFM filenames */
    private const CORE_FONTS = [
        'courier'          => 'courier.afm',
        'courierb'         => 'courierb.afm',
        'courierbi'        => 'courierbi.afm',
        'courieri'         => 'courieri.afm',
        'helvetica'        => 'helvetic__.afm',
        'helveticab'       => 'helveticb.afm',
        'helveticabi'      => 'helveticbi.afm',
        'helveticai'       => 'helvetici.afm',
        'symbol'           => 'symbol_.afm',
        'times'            => 'timesnr_.afm',
        'timesb'           => 'timesnb_.afm',
        'timesbi'          => 'timesnbi_.afm',
        'timesi'           => 'timesni_.afm',
        'zapfdingbats'     => 'zapfdingbats.afm',
    ];

    public function handle(): int
    {
        $outputDir = $this->option('output')
            ? rtrim($this->option('output'), '/')
            : $this->getPackageFontPath();

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Check if fonts already exist
        $existing = glob($outputDir.'/*.json');
        if (!empty($existing) && !$this->option('force')) {
            $this->warn("Font files already exist in {$outputDir}.");
            $this->info('Use --force to regenerate.');

            return self::SUCCESS;
        }

        $this->info('Generating Core14 fonts...');
        $this->info('Source: '.self::AFM_URL);

        // Download AFM zip
        $zipPath = $this->downloadAfmZip();
        if ($zipPath === null) {
            $this->error('Failed to download AFM files.');

            return self::FAILURE;
        }

        // Extract and convert
        $converted = $this->convertAfmToJson($zipPath, $outputDir);

        // Cleanup
        @unlink($zipPath);

        if ($converted === 0) {
            $this->error('No fonts were converted.');

            return self::FAILURE;
        }

        $this->info("Generated {$converted} font files in {$outputDir}");

        return self::SUCCESS;
    }

    private function getPackageFontPath(): string
    {
        return dirname(__DIR__, 3).'/fonts/core';
    }

    private function downloadAfmZip(): ?string
    {
        $zipPath = sys_get_temp_dir().'/core14_afms.zip';

        $this->line('Downloading AFM files...');

        // Try multiple download methods
        if (function_exists('curl_file_get_contents')) {
            $content = @curl_file_get_contents(self::AFM_URL);
        } elseif (function_exists('file_get_contents') && ini_get('allow_url_fopen')) {
            $content = @file_get_contents(self::AFM_URL);
        } else {
            // Fallback: use curl CLI
            $tmpFile = tempnam(sys_get_temp_dir(), 'afm');
            $exitCode = 0;
            exec('curl -sL -o '.escapeshellarg($tmpFile).' '.escapeshellarg(self::AFM_URL), $output, $exitCode);
            if ($exitCode === 0 && filesize($tmpFile) > 0) {
                return $tmpFile;
            }
            $this->error('curl CLI failed.');

            return null;
        }

        if ($content === false || strlen($content) < 100) {
            $this->error('Download failed or empty response.');

            return null;
        }

        file_put_contents($zipPath, $content);

        return $zipPath;
    }

    private function convertAfmToJson(string $zipPath, string $outputDir): int
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            $this->error('Failed to open zip: '.$zip->getStatusString());

            return 0;
        }

        // Extract to temp directory
        $tempDir = sys_get_temp_dir().'/core14_afms_'.uniqid();
        mkdir($tempDir, 0755, true);
        $zip->extractTo($tempDir);
        $zip->close();

        $converted = 0;

        foreach (self::CORE_FONTS as $name => $afmFile) {
            $afmPath = $this->findAfmFile($tempDir, $afmFile);
            if ($afmPath === null) {
                $this->warn("  AFM not found: {$afmFile}");

                continue;
            }

            $jsonData = $this->parseAfm($afmPath, $name);
            if ($jsonData === null) {
                $this->warn("  Failed to parse: {$afmFile}");

                continue;
            }

            $outFile = $outputDir.'/'.$name.'.json';
            file_put_contents($outFile, json_encode($jsonData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $this->info("  ✓ {$name}.json");

            ++$converted;
        }

        // Cleanup temp
        $this->removeDirectory($tempDir);

        return $converted;
    }

    private function findAfmFile(string $dir, string $filename): ?string
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getFilename() === $filename) {
                return $file->getPathname();
            }
        }

        return null;
    }

    private function parseAfm(string $afmPath, string $fontName): ?array
    {
        $content = file_get_contents($afmPath);
        if ($content === false) {
            return null;
        }

        $lines = explode("\n", $content);
        $data = [
            'type' => 'Core',
            'name' => $this->getFontDisplayName($fontName),
            'up' => -100,
            'ut' => 50,
            'dw' => 0,
            'diff' => '',
            'platform_id' => 3,
            'encoding_id' => 1,
            'enc' => '',
            'isUnicode' => false,
            'desc' => [],
            'cbbox' => [],
        ];

        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'AvgCharWidth ')) {
                $data['dw'] = (int) (array_pad(explode(' ', $line), 2, '0')[1] ?? 0);
            }
            if (str_starts_with($line, 'FontBBox ')) {
                $data['desc']['FontBBox'] = preg_replace('/^FontBBox\s+/', '', $line);
            }
            if (str_starts_with($line, 'ItalicAngle ')) {
                $data['desc']['ItalicAngle'] = (int) (array_pad(explode(' ', $line), 2, '0')[1] ?? 0);
            }
            if (str_starts_with($line, 'Ascender ')) {
                $data['desc']['Ascent'] = (int) (array_pad(explode(' ', $line), 2, '0')[1] ?? 0);
            }
            if (str_starts_with($line, 'Descender ')) {
                $data['desc']['Descent'] = (int) (array_pad(explode(' ', $line), 2, '0')[1] ?? 0);
            }
            if (str_starts_with($line, 'CapHeight ')) {
                $data['desc']['CapHeight'] = (int) (array_pad(explode(' ', $line), 2, '0')[1] ?? 0);
            }
            if (str_starts_with($line, 'XHeight ')) {
                $data['desc']['XHeight'] = (int) (array_pad(explode(' ', $line), 2, '0')[1] ?? 0);
            }
            if (str_starts_with($line, 'StdHW ')) {
                $data['desc']['StemH'] = (int) (array_pad(explode(' ', $line), 2, '0')[1] ?? 0);
            }
            if (str_starts_with($line, 'StdVW ')) {
                $data['desc']['StemV'] = (int) (array_pad(explode(' ', $line), 2, '0')[1] ?? 0);
            }
            if (str_starts_with($line, 'StartCharMetrics')) {
                break;
            }
        }

        // Parse character metrics
        $inMetrics = false;
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'StartCharMetrics')) {
                $inMetrics = true;

                continue;
            }
            if (str_starts_with($line, 'EndCharMetrics')) {
                break;
            }
            if (!$inMetrics || empty($line)) {
                continue;
            }

            // Format: C <code> ; WX <width> ; N <name> ; B <llx> <lly> <urx> <ury> ;
            if (preg_match('/^C\s+(\d+)\s*;\s*WX\s+(\d+)\s*;\s*N\s+(\S+)\s*;/', $line, $m)) {
                $code = (int) $m[1];
                $width = (int) $m[2];
                if (preg_match('/B\s+(-?\d+)\s+(-?\d+)\s+(-?\d+)\s+(-?\d+)/', $line, $bbox)) {
                    $data['cbbox'][$code] = [(int) $bbox[1], (int) $bbox[2], (int) $bbox[3], (int) $bbox[4]];
                }
            }
        }

        // Calculate missing width (use average or default)
        if ($data['dw'] === 0 && !empty($data['cbbox'])) {
            $widths = array_map(fn ($cb) => $cb[2] - $cb[0], $data['cbbox']);
            $data['dw'] = (int) (array_sum($widths) / count($widths));
        }

        $data['desc']['MissingWidth'] = $data['dw'];
        $data['desc']['Flags'] = $fontName === 'symbol' ? 4 : 32;
        $data['desc']['AvgWidth'] = $data['dw'];

        // Set encoding for specific fonts
        if ($fontName === 'symbol') {
            $data['enc'] = 'symbol';
        }

        return $data;
    }

    private function getFontDisplayName(string $name): string
    {
        $map = [
            'courier' => 'Courier',
            'courierb' => 'Courier-Bold',
            'courierbi' => 'Courier-BoldOblique',
            'courieri' => 'Courier-Oblique',
            'helvetica' => 'Helvetica',
            'helveticab' => 'Helvetica-Bold',
            'helveticabi' => 'Helvetica-BoldOblique',
            'helveticai' => 'Helvetica-Oblique',
            'symbol' => 'Symbol',
            'times' => 'Times-Roman',
            'timesb' => 'Times-Bold',
            'timesbi' => 'Times-BoldItalic',
            'timesi' => 'Times-Italic',
            'zapfdingbats' => 'ZapfDingbats',
        ];

        return $map[$name] ?? $name;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($dir);
    }
}
