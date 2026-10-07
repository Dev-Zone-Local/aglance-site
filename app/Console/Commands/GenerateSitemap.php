<?php

namespace App\Console\Commands;

use App\Support\Sitemap;
use Illuminate\Console\Command;

class GenerateSitemap extends Command
{
    protected $signature = 'atglance:sitemap {--path= : Folder to write to (default: public/)}';

    protected $description = 'Write sitemap.xml and robots.txt as static files (run on deploy)';

    public function handle(): int
    {
        foreach (Sitemap::write($this->option('path') ?: public_path()) as $file) {
            $this->info("Wrote {$file}");
        }

        return self::SUCCESS;
    }
}
