<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CopyFabricRollsTemplate extends Command
{
    protected $signature = 'template:copy-rolls';
    protected $description = 'Copy legacy plantilla_importacion_rollos.xlsx to public storage path';

    public function handle()
    {
        $source = storage_path('app/legacy/plantilla_importacion_rollos.xlsx');
        $dest = storage_path('app/public/plantilla_importacion_rollos.xlsx');

        if (!File::exists($source)) {
            $this->error("Source file not found: {$source}");

            $files = File::allFiles(storage_path('app'));
            $found = [];
            foreach ($files as $f) {
                if (str_ends_with($f->getFilename(), '.xlsx')) {
                    $found[] = $f->getRealPath();
                }
            }

            if (count($found) > 0) {
                $this->info('Found .xlsx files under storage/app:');
                foreach ($found as $p) {
                    $this->line($p);
                }
            } else {
                $this->info('No .xlsx files found under storage/app');
            }

            return 1;
        }

        File::ensureDirectoryExists(dirname($dest));
        File::copy($source, $dest);

        $this->info("Copied {$source} to {$dest}");
        return 0;
    }
}
