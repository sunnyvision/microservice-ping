<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;

class PingController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Stub
    }

    public function tcp(Request $request, $host, $port) {
        try {
            $latency = microtime(true);
            $status = \App\Ping::pingPort($host, $port);
            return array(
                'success' => true,
                'latency' => ceil((microtime(true) - $latency) * 100000) / 100 . 'ms',
            );
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'host' => $host,
                'port' => $port,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            );
        }
    }

    public function icmp(Request $request, $host) {
        $packets = $request->input('packets', 3);
        $complex = $request->input('complex', 'no');
        $ips = $host ?: null;
        if($packets > 5) $packets = 5;
        if($packets < 1) $packets = 1;

        $ping = array();
        $ping['result'] = array();

        if(empty($ips) || !is_string($ips)) {
            return array(
                'error' => 'you have to specify a host at ?host'
            );
        }

        $rpi = (\App\Ping::localPing($ips, 3, $packets));
        if($complex == 'yes') {
            $ping['result'] = $rpi;
        } else {
            $ping['result']= array(
                'resolved' => $rpi['resolved'],
                'avg' => $rpi['result']['avg'],
                'packet_loss' => $rpi['result']['latency']['packet_loss_percentage'],
            );
        }

        $ping['success'] = isset($rpi['result']['latency']['packet_loss_percentage']) && $rpi['result']['latency']['packet_loss_percentage'] == 0 ? true : false;
        $ping['descriptor'] = env('DESCRIPTOR');
        $ping['version'] = '(c) sunnyvision - ' . env('VERSION');
        $ping['answered_at'] = date("Y-m-d H:i:s");
        $ping['queried_by'] = empty($_SERVER['X_HTTP_FORWARDED_FOR']) ? $_SERVER['REMOTE_ADDR'] : $_SERVER['X_HTTP_FORWARDED_FOR'];

        return $ping;
    }

}
