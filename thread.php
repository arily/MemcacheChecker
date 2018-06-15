<?php
if (threadfound($argv) == true) {
	die(thread($argv[1], $argv[2], $argv[3]));
} else {
	die(start_threading($argv[1], $argv[2], $argv[3], $argv[4]));
}