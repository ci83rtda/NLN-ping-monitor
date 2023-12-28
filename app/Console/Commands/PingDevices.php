<?php

namespace App\Console\Commands;

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

             $this->info("Now pinging: $device->ipAddress");
             $ping = Ping::run($device->ipAddress);
             $this->info("Ping Response: ".$ping .' = ' . ($ping  == 1 ? "active" : "inactive"));
             $device->update(['query_date' => now()]);
             $client = new Client([
                 'base_uri' => config('services.uisp.url'),
                 'headers' => ['x-auth-token' => config('services.uisp.token')]
             ]);

             if ($device->status != ($ping == 1 ? 1 : 0)) {

                 $data = [
                     "deviceId" => $device->deviceId,
                     "hostname" => $device->hostname,
                     "modelName" => $device->modelName,
                     "systemName" => "pi-monitor",
                     "vendorName" => $device->vendorName,
                     "ipAddress" => $device->ipAddress,
                     "macAddress" => $device->macAddress,
                     "deviceRole" => $device->deviceRole,
                     "siteId" => $device->siteId,
                     "pingEnabled" => $ping == 1 ? false : true,
                     "ubntDevice" => false,
                     "ubntData" => [
                         "firmwareVersion" => "0",
                         "model" => "blackbox"
                     ],
                     "snmpCommunity" => "public",
                     "note" => "CPE",
                     "interfaces" => [
                         [
                             "id" => "eth0",
                             "position" => 0,
                             "name" => "eth1",
                             "mac" => $device->macAddress,
                             "type" => "eth",
                             "addresses" => [
                                 "{$device->ipAddress}/24"
                             ]
                         ]
                     ]
                 ];
                 $this->info("Now updating...");
                 $response = $client->request('PUT', "devices/blackboxes/{$device->deviceId}/config", ['json' => $data]);
                 $device->update(['status' => (boolean) $ping]);
                 $this->info("done!");
             }

         }
        $this->info("All Set!");
    }
}
