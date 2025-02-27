<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Illuminate\Support\Facades\File;

class ClearLog extends Command
{
    protected $signature = 'log:clear';
    protected $description = 'Clear the Laravel log file';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $logPath = storage_path('logs/laravel.log');

        if (File::exists($logPath)) {
            File::put($logPath, '');
            $this->info('Laravel log cleared.');
        } else {
            $this->info('Laravel log file does not exist.');
        }
    }
}
