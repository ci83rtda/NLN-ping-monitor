<?php

namespace App\Jobs;

use App\Models\Device;
use App\Services\Ping;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Process\Process;

class PingModemIP implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $IpAddress;
    public $deviceData;
    /**
     * Create a new job instance.
     */
    public function __construct($IpAddress, $deviceData)
    {
        $this->IpAddress = $IpAddress;
        $this->deviceData = $deviceData;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {

        try {
            $device = Device::where('ipAddress', $this->IpAddress)->first();
            if (is_null($device)) {
                return;
            }

            $process = new Process(["/usr/bin/ping", "-c 1", $this->IpAddress]);
            $process->run();
            $ping = $process->isSuccessful() == 1 ? 1 : 0;
            //dd($process->getOutput());

            \Log::info("checking {$this->IpAddress}, response {$ping}");
            \Log::info("output ".json_encode($process->getOutput()));

            if($this->deviceData['status'] != $ping){
                \Log::info("we update... {$this->IpAddress}");

                $device->update(['query_date' => now()]);

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
                \Log::info(json_encode($response->getBody()->getContents()));
                $device->update(['status' => (boolean) $ping]);

            }

        } catch (\Exception $e) {
            // Log the exception
            \Log::error('PingModemIP job failed: ' . $e->getMessage());

            // Optionally rethrow the exception if you want to trigger a retry
            //throw $e;
        }
    }
}
