<?php

namespace App\Console\Commands;

use App\Models\Device;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

class GetDevices extends Command
{
    protected $signature = 'app:get-devices';

    protected $description = 'Get all third-party blackbox router devices and sync them locally';

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

            $blackbox_device = json_decode($response->getBody()->getContents());

            if (($blackbox_device->systemName ?? null) !== 'pi-monitor') {
                continue;
            }

            $addresses = $blackbox_device->interfaces[0]->addresses ?? [];

            Device::updateOrCreate(
                ['deviceId' => $device->identification->id],
                [
                    'siteId' => $blackbox_device->siteId,
                    'status' => $device->overview->status == 'active' ? 1 : 0,
                    'ipAddress' => $device->ipAddress,
                    'cidrIpAddress' => $addresses,
                    'macAddress' => $blackbox_device->macAddress,
                    'hostname' => $blackbox_device->hostname,
                    'modelName' => $blackbox_device->modelName,
                    'vendorName' => $blackbox_device->vendorName,
                    'deviceRole' => $blackbox_device->deviceRole,
                    'pingEnabled' => $blackbox_device->pingEnabled,
                ]
            );

            $this->info("Synced device {$device->identification->id}");
        }

        return self::SUCCESS;
    }
}
