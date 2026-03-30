<?php

namespace App\Console\Commands;

use App\Models\Device;
use GuzzleHttp\Client;
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
        $IpAddress = $this->ask('What is your IP?');

        $update = $this->choice('Update the DB?', ['yes', 'no']);

        if($update != 'yes') {

            $process = new Process(["/usr/bin/ping", "-c 1", $IpAddress]);
            $process->run();
            $this->info('Output: '.$process->getOutput());
            $this->info('isSuccessful:  '.$process->isSuccessful());
            $this->info('IP:  '.$IpAddress);

        }else{

            try {
                $device = Device::where('ipAddress', $IpAddress)->first();
                if (is_null($device)) {
                    return;
                }

                $process = new Process(["/usr/bin/ping", "-c 1", $IpAddress]);
                $process->run();
                $ping = $process->isSuccessful() == 1 ? 1 : 0;
                //dd($process->getOutput());

                $this->info('Output: '.$process->getOutput());
                $this->info('isSuccessful:  '.$process->isSuccessful());
                $this->info('IP:  '.$IpAddress);

//            Log::info("checking {$this->IpAddress}, response {$ping}");
//            Log::info("output ".json_encode($process->getOutput()));

                if($device->status != $ping){
                    $this->info("we update... {$IpAddress}");

                    //$device->update(['query_date' => now()]);

                    $client = new Client([
                        'base_uri' => config('services.uisp.url'),
                        'headers' => ['x-auth-token' => config('services.uisp.token')]
                    ]);

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
                        "pingEnabled" => !($ping == 1),
                        "ubntDevice" => false,
                        "ubntData" => [
                            "firmwareVersion" => "0",
                            "model" => "blackbox"
                        ],
                        "snmpCommunity" => "public",
                        "note" => "Fiber CPE",
                        "interfaces" => [
                            [
                                "id" => "eth0",
                                "position" => 0,
                                "name" => "eth1",
                                "mac" => $device->macAddress,
                                "type" => "eth",
                                "addresses" => $device->cidrIpAddress
                            ]
                        ]
                    ];

                    $response = $client->request('PUT', "devices/blackboxes/{$device->deviceId}/config", ['json' => $data]);
//                \Log::info(json_encode($response->getBody()->getContents()));
                    $device->update(['status' => (boolean) $ping, 'query_date' => now()]);

                }

            } catch (\Exception $e) {
                // Log the exception
                $this->error('PingModemIP job failed: ' . $e->getMessage());

                // Optionally rethrow the exception if you want to trigger a retry
                //throw $e;
            }

        }



    }
}
