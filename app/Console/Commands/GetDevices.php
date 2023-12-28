<?php

namespace App\Console\Commands;

use App\Models\Device;
use Faker\Factory;
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
    protected $description = 'get all devices that are third party and need to be pinged manually to modify the device status';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        $client = new Client([
            'base_uri' => config('services.uisp.url'),
            'headers' => ['x-auth-token' => config('services.uisp.token')]
        ]);

        $response = $client->request('GET', "devices?withInterfaces=false&authorized=true&type=blackBox&role=router");

        $devices = json_decode($response->getBody()->getContents());


        foreach ($devices as $device){

            $response = $client->request('GET', "devices/blackboxes/{$device->identification->id}/config");
            $blackbox_device = json_decode($response->getBody()->getContents());

            $this->info($device->identification->id);

            if (!Device::where('deviceId', $device->identification->id)->count() && $blackbox_device->systemName == 'pi-monitor' ){
                $faker = Factory::create();
                Device::create([
                    'deviceId' => $device->identification->id,
                    'siteId' => $blackbox_device->siteId,
                    'status' => $device->overview->status == 'active' ? 1 : 0,
                    'ipAddress' => $device->ipAddress,
                    'macAddress' => strtolower($faker->macAddress()),
                    'hostname' => $blackbox_device->hostname,
                    'modelName' => $blackbox_device->modelName,
                    'vendorName' => $blackbox_device->vendorName,
                    'deviceRole' => $blackbox_device->deviceRole,
                    'pingEnabled' => $blackbox_device->pingEnabled,


                ]);
                $this->info("added");
            }

        }

    }
}
