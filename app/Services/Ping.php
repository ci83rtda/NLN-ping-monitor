<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class Ping
{

    /**
     * @param string $ip
     * @return bool
     */
    public static function run(string $ip): bool
    {
        $output = array();
        $status = null;

        $process = new Process(["/sbin/ping", "-c 1", escapeshellarg($ip)]);
        $process->run();

        return $process->isSuccessful();

//        exec("ping -c 4 " . escapeshellarg($ip) . " 2>&1", $output, $status);
//
//        return $status === 0;
    }

}
