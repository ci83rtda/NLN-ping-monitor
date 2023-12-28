<?php

namespace App\Services;

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

        exec("ping -c 4 " . escapeshellarg($ip) . " 2>&1", $output, $status);

        return $status === 0;
    }

}
