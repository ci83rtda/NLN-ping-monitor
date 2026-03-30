<?php

namespace App\Console\Commands;

use App\Jobs\PingModemIP;
use App\Models\Device;
use App\Services\Ping;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

class PingDevices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ping-devices';

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
        Device::select('deviceId')
            ->chunk(500, function ($devices) {
                foreach ($devices as $device) {
                    PingModemIP::dispatch($device->deviceId)->onQueue('monitor');
                }
            });

        return self::SUCCESS;
    }
}
