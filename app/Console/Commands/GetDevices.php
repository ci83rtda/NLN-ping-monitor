<?php

namespace App\Console\Commands;

use App\Models\Device;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

class GetDevices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:get-devices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get all third-party blackbox router devices and sync them locally';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $client = new Client([
            'base_uri' => config('services.uisp.url'),
            'headers' => ['x-auth-token' => config('services.uisp.token')],
            'timeout' => 15,
        ]);

        $response = $client->request(
            'GET',
            'devices?withInterfaces=false&authorized=true&type=blackBox&role=router'
        );

        $devices = json_decode($response->getBody()->getContents());

        foreach ($devices as $device) {
            $response = $client->request(
                'GET',
                "devices/blackboxes/{$device->identification->id}/config"
            );

            $blackboxDevice = json_decode($response->getBody()->getContents());

            if (($blackboxDevice->systemName ?? null) !== 'pi-monitor') {
                continue;
            }

            $addresses = $blackboxDevice->interfaces[0]->addresses ?? [];

            Device::updateOrCreate(
                ['deviceId' => $device->identification->id],
                [
                    'siteId' => $blackboxDevice->siteId,
                    'status' => $device->overview->status == 'active' ? 1 : 0,
                    'ipAddress' => $device->ipAddress,
                    'cidrIpAddress' => $addresses,
                    'macAddress' => $blackboxDevice->macAddress,
                    'hostname' => $blackboxDevice->hostname,
                    'modelName' => $blackboxDevice->modelName,
                    'vendorName' => $blackboxDevice->vendorName,
                    'deviceRole' => $blackboxDevice->deviceRole,
                    'pingEnabled' => $blackboxDevice->pingEnabled,
                ]
            );

            $this->info("Synced device {$device->identification->id}");
        }

        return self::SUCCESS;
    }
}
