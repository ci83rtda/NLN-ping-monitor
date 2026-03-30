<?php

namespace App\Console\Commands;

use App\Models\Device;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class TestPing extends Command
{
    protected $signature = 'app:test-ping';

    protected $description = 'Test a single device ping and optionally update UISP and the local DB';

    public function handle()
    {
        $ipAddress = trim($this->ask('What is your IP?'));
        $update = $this->choice('Update UISP and the DB?', ['yes', 'no'], 'no');

        if (empty($ipAddress)) {
            $this->error('IP address is required.');
            return self::FAILURE;
        }

        if ($update !== 'yes') {
            $process = new Process([
                '/usr/bin/ping',
                '-c', '1',
                '-W', '2',
                $ipAddress,
            ]);

            $process->setTimeout(5);
            $process->run();

            $this->line('----------------------------------------');
            $this->info('Ping-only test');
            $this->line('IP: ' . $ipAddress);
            $this->line('Exit code: ' . $process->getExitCode());
            $this->line('Successful: ' . ($process->isSuccessful() ? 'yes' : 'no'));
            $this->line('STDOUT:');
            $this->line(trim($process->getOutput()) ?: '[empty]');
            $this->line('STDERR:');
            $this->line(trim($process->getErrorOutput()) ?: '[empty]');
            $this->line('----------------------------------------');

            return self::SUCCESS;
        }

        try {
            $device = Device::where('ipAddress', $ipAddress)->first();

            if (is_null($device)) {
                $this->error("No device found in DB with IP {$ipAddress}");
                return self::FAILURE;
            }

            $process = new Process([
                '/usr/bin/ping',
                '-c', '1',
                '-W', '2',
                $ipAddress,
            ]);

            $process->setTimeout(5);
            $process->run();

            $ping = $process->isSuccessful() ? 1 : 0;

            $this->line('----------------------------------------');
            $this->info('Ping + update test');
            $this->line('Device UUID: ' . $device->uuid);
            $this->line('Device ID: ' . $device->deviceId);
            $this->line('IP: ' . $ipAddress);
            $this->line('Current DB status: ' . (int) $device->status);
            $this->line('Ping result: ' . $ping);
            $this->line('Exit code: ' . $process->getExitCode());
            $this->line('Successful: ' . ($process->isSuccessful() ? 'yes' : 'no'));
            $this->line('STDOUT:');
            $this->line(trim($process->getOutput()) ?: '[empty]');
            $this->line('STDERR:');
            $this->line(trim($process->getErrorOutput()) ?: '[empty]');
            $this->line('----------------------------------------');

            $device->update([
                'query_date' => now(),
            ]);

            if ((int) $device->status === (int) $ping) {
                $this->info('No status change detected. No UISP update needed.');
                return self::SUCCESS;
            }

            $this->warn("Status changed, updating UISP for {$ipAddress}...");

            $client = new Client([
                'base_uri' => config('services.uisp.url'),
                'headers' => [
                    'x-auth-token' => config('services.uisp.token'),
                ],
                'timeout' => 10,
            ]);

            $response = $client->request(
                'GET',
                "devices/blackboxes/{$device->deviceId}/config"
            );

            $remoteConfig = json_decode($response->getBody()->getContents(), true);

            if (! is_array($remoteConfig)) {
                $this->error('Could not read valid UISP config response.');
                return self::FAILURE;
            }

            $this->line('UISP current siteId: ' . ($remoteConfig['siteId'] ?? '[missing]'));
            $this->line('Local DB siteId: ' . ($device->siteId ?? '[missing]'));

            if (isset($remoteConfig['siteId']) && $device->siteId != $remoteConfig['siteId']) {
                $device->update([
                    'siteId' => $remoteConfig['siteId'],
                ]);
                $device->siteId = $remoteConfig['siteId'];

                $this->warn('Local DB siteId updated from UISP before PUT.');
            }

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

            $putResponse = $client->request(
                'PUT',
                "devices/blackboxes/{$device->deviceId}/config",
                ['json' => $data]
            );

            $device->update([
                'status' => (bool) $ping,
                'pingEnabled' => $data['pingEnabled'],
                'query_date' => now(),
            ]);

            $fresh = $device->fresh();

            $this->info('UISP update completed.');
            $this->line('PUT HTTP status: ' . $putResponse->getStatusCode());
            $this->line('New DB status: ' . (int) $fresh->status);
            $this->line('New DB pingEnabled: ' . (int) $fresh->pingEnabled);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('TestPing failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
