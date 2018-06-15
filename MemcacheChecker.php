<?php
/** Memcached tester
 *
 * a tool finds global memcached server and make a list
 *
 * useage:
 *	edit the first line in the "thread" function
 *		LengthMemcachedStats is for testing server responding
 *		MemcachedTester is for a full test includs connecting, write random bytes, and request single key using UDP method.
 *		a detailed log will output to ./log
 *
 * @author Layer4
 * @mod arily
 *
 */
//udp stats header length = 20l
//upd get header depends on your query string length.
function thread($ip, $output, $responselength) {
	//stats,Tester
	$len = MemcachedTester($ip, TEST_TIMEOUT);
	if ($len >= $responselength) {
		addentry($output, $ip);
		print($ip . " " . $len . " [x" . round($len / QUERY_LENGTH, 2) . "]\n");
	}
}
include __DIR__ . "/libmemc.php";
include __DIR__ . "/thread.php";