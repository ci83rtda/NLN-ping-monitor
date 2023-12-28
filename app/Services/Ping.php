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

        exec("ping " . escapeshellarg($ip) . " 2>&1", $output, $status);

        return $status === 0;
    }

}
