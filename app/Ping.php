<?php

namespace App;

class Ping {
	// ICMP ping packet with a pre-calculated checksum
	protected static $payload = "\x08\x00\x7d\x4b\x00\x00\x00\x00PingHost";
	/**
	 * Send a ping request to a host.
	 *
	 * @param	string	$host		Host name or IP address to ping
	 * @param	int		$timeout	Timeout for ping in seconds
	 * @return	bool				True if ping succeeds, false if not
	 */
	public static function send($host, $timeout = 1) {
		if (extension_loaded('sockets')) {
			return self::socketSend($host, $timeout);
		} else {
			return self::execSend($host);
		}
	}
	/**
	 * Use sockets to ping a host.
	 *
	 * Will call function to use exec to send ping request if the socket request fails.
	 * Socket request will fail if it is unable to find the host.
	 *
	 * Using sockets under Windows requires that the Application Pool in IIS be running under an account with local admin rights.
	 *
	 * @param	string	$host		Host name or IP address to ping
	 * @param	int		$timeout	Timeout for ping in seconds
	 * @return	bool				True if ping succeeds, false if not
	 */
	protected static function socketSend($host, $timeout) {
		try {
			$socket = socket_create(AF_INET, SOCK_RAW, 1);
			socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => $timeout, 'usec' => 0));
			socket_connect($socket, $host, null);
			socket_send($socket, self::$payload, strLen(self::$payload), 0);
			$result = socket_read($socket, 255) ? true : false;
			socket_close($socket);
			return $result;
		} catch (Exception $e) {
			return self::execSend($host);
		}
	}
	/**
	 * Use exec to ping a host.
	 *
	 * Ping command is specific to Windows host.
	 *
	 * @param	string	$host	Host name or IP address to ping
	 * @return	bool			True if ping succeeds, false if not
	 */
	public static function execSend($host) {
		$command = escapeshellcmd('ping -n 1 -w 1 ' . $host);
		exec($command, $result, $returnCode);
		return self::arraySearch($result, 'received = 1') ? true : false;
	}

	protected static function arraySearch($array, $searchTerm) {
		foreach ($array as $value) {
			$value = strtolower($value);
			$searchTerm = strtolower($searchTerm);
			if (strpos($value, $searchTerm)) {
				return true;
			}
		}

		return false;
	}

	public static function localPingByTryTimeout($host, $try = 1, $timeout = 1) {
		return self::localPing($host, $timeout, $try);
	}

	public static function localPing($host, $timeout = 1, $try = 1) {
		$pingResult = exec(
				sprintf('ping -c %d -W %d %s 2>&1', 
				intval($try), 
				intval($timeout), 
				escapeshellarg($host)
			), $res, $rval);
		$re = '/= (.*)\/(.*)\/(.*)\/(.*) (.*)/';
		$str = $pingResult;


		preg_match_all($re, $str, $matches, PREG_SET_ORDER, 0);

		$latency = array(
			'received' => -1,
			'transmitted' => -1,
			'packet_loss_percentage' => -1,
			'time_ms' => -1,
			);

		foreach($res as $r) {
			$re = '/(.*) packets transmitted, (.*) received, (.*)% packet loss, time (.*)ms/';
			preg_match_all($re, $r, $latencyMatches, PREG_SET_ORDER, 0);
			if(!empty($latencyMatches)) {
				$latency['packet_loss_percentage'] = intval($latencyMatches[0][3]);
				$latency['received'] = intval($latencyMatches[0][2]);
				$latency['transmitted'] = intval($latencyMatches[0][1]);
				$latency['time_ms'] = intval($latencyMatches[0][4]);
				break;
			}
		}
		if(!empty($matches)) {
			$result = array(
				'latency' => $latency,
				'min' => $matches[0][1],
				'avg' => $matches[0][2],
				'max' => $matches[0][3],
				'mdev' => $matches[0][4],
				'unit' => $matches[0][5],
				);
		} else {
			$result = array(
				'latency' => $latency,
				'min' => null,
				'avg' => null,
				'max' => null,
				'mdev' => null,
				'unit' => null
			);
		}
		return array(
			'rval' => $rval,
			'str' => $str,
			'host' => $host,
			'resolved' => gethostbyname($host),
			'result' => $result,
			'succeed' => $rval === 0
			);
	}

	public static function pingPort($ip, $port)
	{
		$starttime = microtime(true);
		$file      = @fsockopen ($ip, $port, $errno, $errstr, 10);
		$stoptime  = microtime(true);
		$status    = 0;
		if (!$file) throw new \Exception("Cannot reach {$ip}:{$port} (ERR-{$errno} :$errstr)", 1);  
		else {
			fclose($file);
			$status = ($stoptime - $starttime) * 1000;
			$status = floor($status);
		}
		return $status;
	}
}