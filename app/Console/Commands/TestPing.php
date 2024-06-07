<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class TestPing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-ping';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $ip = $this->ask('What is your IP?');

        $process = new Process(["/usr/bin/ping", "-c 1", escapeshellarg($ip)]);

        $process->run();
        dd($process);
        $this->info('Output: '.$process->getOutput());
        $this->info('isSuccessful:  '.$process->isSuccessful());
        $this->info('IP:  '.escapeshellarg($ip));

    }
}
