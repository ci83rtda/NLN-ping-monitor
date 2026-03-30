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

    public string $deviceRowId;
    public int $uniqueFor = 240; // 4 minutes

    public function __construct(string $deviceRowId)
    {
        $this->deviceRowId = $deviceRowId;
    }

    public function uniqueId(): string
    {
        return (string) $this->deviceRowId;
    }

    public function handle(): void
    {
        try {
            $device = Device::find($this->deviceRowId);

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

            // Always update last check time
            $device->update([
                'query_date' => now(),
            ]);

            // Compare against CURRENT db value, not stale queued data
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

            /*
             * Very important:
             * Fetch latest config from UISP first so we use the CURRENT siteId
             * and do not overwrite a device that was moved to another client/site.
             */
            $response = $client->request('GET', "devices/blackboxes/{$device->deviceId}/config");
            $remoteConfig = json_decode($response->getBody()->getContents(), true);

            if (! is_array($remoteConfig)) {
                \Log::warning('PingModemIP: invalid UISP config response', [
                    'device_row_id' => $this->deviceRowId,
                    'deviceId' => $device->deviceId,
                ]);
                return;
            }

            // Keep local DB siteId synced to the latest UISP siteId
            if (isset($remoteConfig['siteId']) && $device->siteId != $remoteConfig['siteId']) {
                $device->update([
                    'siteId' => $remoteConfig['siteId'],
                ]);
                $device->siteId = $remoteConfig['siteId'];
            }

            /*
             * Use the latest UISP config as the base payload, then only override
             * the fields you control.
             */
            $data = $remoteConfig;

            $data['deviceId'] = $device->deviceId;
            $data['hostname'] = $device->hostname;
            $data['modelName'] = $device->modelName;
            $data['systemName'] = 'pi-monitor';
            $data['vendorName'] = $device->vendorName;
            $data['ipAddress'] = $device->ipAddress;
            $data['macAddress'] = $device->macAddress;
            $data['deviceRole'] = $device->deviceRole;

            // Use current UISP siteId, not stale local siteId
            $data['siteId'] = $remoteConfig['siteId'] ?? $device->siteId;

            /*
             * Your workaround:
             * - if ping succeeds => pingEnabled false
             * - if ping fails    => pingEnabled true
             */
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
            \Log::error('PingModemIP job failed', [
                'device_row_id' => $this->deviceRowId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
