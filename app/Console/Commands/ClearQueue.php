<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clear-queue';

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
        $count = DB::table('jobs')->count();

        if ($count > 0) {
            // Delete all jobs from the database
            DB::table('jobs')->delete();
            $this->info("All $count pending jobs have been cleared from the queue.");
        } else {
            $this->info('No pending jobs in the queue.');
        }
    }
}
