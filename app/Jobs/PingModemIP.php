<?php

namespace App\Jobs;

use App\Models\Device;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Process\Process;

class PingModemIP implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $deviceId;
    public int $uniqueFor = 240; // prevent overlap for 4 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(string $deviceId)
    {
        $this->deviceId = $deviceId;
    }

    public function uniqueId(): string
    {
        return $this->deviceId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $device = Device::where('deviceId', $this->deviceId)->first();

            if (! $device || empty($device->ipAddress) || empty($device->deviceId)) {
                return;
            }

            $process = new Process([
                '/usr/bin/ping',
                '-c', '1',
                '-W', '2',
                $device->ipAddress,
            ]);

            $process->setTimeout(5);
            $process->run();

            $ping = $process->isSuccessful() ? 1 : 0;

            // always store last check time
            $device->update([
                'query_date' => now(),
            ]);

            // compare against CURRENT database status
            if ((int) $device->status === (int) $ping) {
                return;
            }

            $client = new Client([
                'base_uri' => config('services.uisp.url'),
                'headers' => [
                    'x-auth-token' => config('services.uisp.token'),
                ],
                'timeout' => 10,
            ]);

            // fetch latest UISP config first to avoid stale siteId pushbacks
            $response = $client->request(
                'GET',
                "devices/blackboxes/{$device->deviceId}/config"
            );

            $remoteConfig = json_decode($response->getBody()->getContents(), true);

            if (! is_array($remoteConfig)) {
                \Log::warning('PingModemIP: invalid UISP config response', [
                    'deviceId' => $this->deviceId,
                ]);
                return;
            }

            // sync local siteId to current UISP siteId
            if (isset($remoteConfig['siteId']) && $device->siteId != $remoteConfig['siteId']) {
                $device->update([
                    'siteId' => $remoteConfig['siteId'],
                ]);
                $device->siteId = $remoteConfig['siteId'];
            }

            // use current UISP config as the base
            $data = $remoteConfig;

            $data['deviceId'] = $device->deviceId;
            $data['hostname'] = $device->hostname;
            $data['modelName'] = $device->modelName;
            $data['systemName'] = 'pi-monitor';
            $data['vendorName'] = $device->vendorName;
            $data['ipAddress'] = $device->ipAddress;
            $data['macAddress'] = $device->macAddress;
            $data['deviceRole'] = $device->deviceRole;
            $data['siteId'] = $remoteConfig['siteId'] ?? $device->siteId;

            // your UISP workaround
            $data['pingEnabled'] = ! ((int) $ping === 1);

            $data['ubntDevice'] = false;
            $data['ubntData'] = [
                'firmwareVersion' => '0',
                'model' => 'blackbox',
            ];
            $data['snmpCommunity'] = 'public';
            $data['note'] = 'Fiber CPE';
            $data['interfaces'] = [
                [
                    'id' => 'eth0',
                    'position' => 0,
                    'name' => 'eth1',
                    'mac' => $device->macAddress,
                    'type' => 'eth',
                    'addresses' => $device->cidrIpAddress,
                ]
            ];

            $client->request(
                'PUT',
                "devices/blackboxes/{$device->deviceId}/config",
                ['json' => $data]
            );

            $device->update([
                'status' => (bool) $ping,
                'pingEnabled' => $data['pingEnabled'],
            ]);
        } catch (\Throwable $e) {
            \Log::error('PingModemIP job failed [$device->ipAddress]', [
                'deviceId' => $this->deviceId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
