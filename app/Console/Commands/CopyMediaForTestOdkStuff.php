<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CopyMediaForTestOdkStuff extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:copy-media-test {programatic?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Copies media files that match the TestWithMiniForms database seeder';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $folderPath = base_path('tests/assets/media-for-mini-forms');

        $this->info('Copying media files for TestWithMiniForms seeder');

        if (! $this->argument('programatic')) {

            $this->info('This may overwrite existing files in storage/app/');
            $this->info('Are you sure you want to continue?');
            if ($this->confirm('Continue?')) {
                $this->copyMediaFiles($folderPath);
            }
        } else {
            $this->copyMediaFiles($folderPath);
        }

    }

    public function copyMediaFiles(string $sourcePath): void
    {

        $destinationPath = storage_path('app');

        if (! is_dir($sourcePath)) {
            $this->error('Source directory does not exist.');

            return;
        }

        $files = scandir($sourcePath);

        if ($files === false) {
            $this->error('Unable to read source directory.');

            return;
        }

        $this->recursiveCopy($sourcePath, $destinationPath);

        $this->info('Media files copied successfully.');
    }

    // Thanks @gserrano :) https://gist.github.com/gserrano/4c9648ec9eb293b9377b
    public function recursiveCopy($src, $dest): void
    {
        $dir = opendir($src);
        @mkdir($dest);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src.'/'.$file)) {
                    $this->recursiveCopy($src.'/'.$file, $dest.'/'.$file);
                } else {
                    copy($src.'/'.$file, $dest.'/'.$file);
                }
            }
        }
        closedir($dir);
    }
}
