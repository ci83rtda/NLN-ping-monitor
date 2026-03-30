<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckPendingJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-pending-jobs';

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
        $count = DB::table('jobs')->whereNull('reserved_at')->count();
//        $this->info($count . ' jobs are pending in the queue.');

        if ($count > 0) {
            $jobs = DB::table('jobs')->whereNull('reserved_at')->get();
            foreach ($jobs as $job) {
                $this->line("Job ID: {$job->id}, Job Payload: " . json_decode($job->payload)->displayName);
            }
        }
    }
}
