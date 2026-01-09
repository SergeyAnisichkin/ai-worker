<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestRunCommand extends Command
{
    protected $signature = 'tt:tt';

    protected $description = 'Test run command';

    public function handle(): int
    {
        $this->info('Test run command!');
        Log::info('Test run command+');

        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "test_{$timestamp}.txt";
        $filePath = storage_path("app/{$filename}");

        file_put_contents($filePath, "Файл создан в: " . now());

        return self::SUCCESS;
    }
}
