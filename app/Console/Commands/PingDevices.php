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
         $devices = Device::all();
//         $devices = Device::where('ipAddress', '172.16.42.6')->get();

         foreach ($devices as $device){
             PingModemIP::dispatch($device->ipAddress, $device->toArray())->onQueue('pinging')->onQueue('monitor');
         }
        $this->info("All Set!");
    }
}
