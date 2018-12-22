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

	public static function localTraceRoute($host, $hop = 30, $probe = 2, $wait = 1) {
		$pingResult = exec(
				sprintf('traceroute -I %s -m %d -q %d -w %d 2>&1', 
				escapeshellarg($host),
				intval($hop),
				intval($probe),
				intval($wait)
			), $res, $rval);

		$result = array(
			'result' => array(),
		);

		$lastHop = 0;
		foreach($res as $r) {
			$re = '/(\d+)\s+(.*?)\s+\((.*)\)\s+(.*)/m';
			preg_match_all($re, $r, $probeMatches, PREG_SET_ORDER, 0);
			$latency = array();
			if(!empty($probeMatches)) {
				$latency['hop'] = intval($probeMatches[0][1]);
				$lastHop = $latency['hop'];
				$latency['source_addr'] = ($probeMatches[0][2]);
				$latency['source_ip'] = ($probeMatches[0][3]);
				$rtt = $probeMatches[0][4];
				$rtt = str_replace("ms", "  ", ($rtt));
				$rtt = str_replace("  ", " ", ($rtt));
				$latency['rtt'] = array_values(array_filter(explode(" ", str_replace("  ", " ", ($rtt)))));
			} else if($lastHop != 0) {
				$latency['hop'] = ++$lastHop;
				$latency['error'] = 'timeout';
			}
			$result['result'][] = $latency;
		}

		$result['raw'] = implode(PHP_EOL, $res);
		return $result;
	}

	public static function traceRoute($host, $hop = 30) {

	    define ("SOL_IP", 0);
	    define ("IP_TTL", 2);   

	    $dest_url = $host;   // Fill in your own URL here, or use $argv[1] to fetch from commandline.
	    $maximum_hops = $hop;
	    $port = 33434;  // Standard port that traceroute programs use. Could be anything actually.

	    // Get IP from URL
	    $dest_addr = gethostbyname ($dest_url);
	    	
	    $return = array();

	    $return['resolved'] = $dest_addr;

	    $return['result'] = array();

	    $ttl = 1;
	    while ($ttl < $maximum_hops) {
	        // Create ICMP and UDP sockets
	        $recv_socket = socket_create (AF_INET, SOCK_RAW, getprotobyname ('icmp'));
	        $send_socket = socket_create (AF_INET, SOCK_DGRAM, getprotobyname ('udp'));

	        // Set TTL to current lifetime
	        socket_set_option ($send_socket, SOL_IP, IP_TTL, $ttl);

	        // Bind receiving ICMP socket to default IP (no port needed since it's ICMP)
	        socket_bind ($recv_socket, 0, 0);

	        // Save the current time for roundtrip calculation
	        $t1 = microtime (true);

	        // Send a zero sized UDP packet towards the destination
	        socket_sendto ($send_socket, "", 0, 0, $dest_addr, $port);

	        // Wait for an event to occur on the socket or timeout after 5 seconds. This will take care of the
	        // hanging when no data is received (packet is dropped silently for example)
	        $r = array ($recv_socket);
	        $w = $e = array ();
	        socket_select ($r, $w, $e, 5, 0);

	        // Nothing to read, which means a timeout has occurred.
	        if (count ($r)) {
	            // Receive data from socket (and fetch destination address from where this data was found)
	            socket_recvfrom ($recv_socket, $buf, 512, 0, $recv_addr, $recv_port);

	            // Calculate the roundtrip time
	            $roundtrip_time = (microtime(true) - $t1) * 1000;

	            // No decent address found, display a * instead
	            if (empty ($recv_addr)) {
	                $recv_addr = "*";
	                $recv_name = "*";
	            } else {
	                // Otherwise, fetch the hostname for the address found
	                $recv_name = gethostbyaddr ($recv_addr);
	            }

	            // Print statistics
	            $return['result'][]  = array(
	            	'success' => true,
	            	'ttl' => $ttl,
	            	'recv_addr' => $recv_addr,
	            	'roundtrip_time' => $roundtrip_time,
	            	'recv_name' => $recv_name,
	            	'formatted' => sprintf ("%3d   %-15s  %.3f ms  %s\n", $ttl, $recv_addr,  $roundtrip_time, $recv_name),
	            );
	        } else {
	            // A timeout has occurred, display a timeout
	            
	            $return['result'][]  = array(
	            	'success' => false,
	            	'ttl' => $ttl,
	            	'error' => 'timeout',
	            	'formatted' => sprintf ("%3d   (timeout)\n", $ttl),
	            );
	        }

	        // Close sockets
	        socket_close ($recv_socket);
	        socket_close ($send_socket);

	        // Increase TTL so we can fetch the next hop
	        $ttl++;

	        // When we have hit our destination, stop the traceroute
	        if ($recv_addr == $dest_addr) break;
	    }

	    return $return;
	}
}