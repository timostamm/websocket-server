
quick:
	ulimit -n 2048 && php fuzzing/server.php 8000 StreamSelect &
	sleep 1
	wstest -m fuzzingclient -s fuzzing/quick.json
	killall php

profile:
	php -d 'xdebug.profiler_enable=1' fuzzing/server.php 8000 LibEvent &
	sleep 1
	wstest -m fuzzingclient -s fuzzing/profile.json
	killall php

complete:
	ulimit -n 2048 && php fuzzing/server.php 8001 LibEvent &
	ulimit -n 2048 && php fuzzing/server.php 8002 StreamSelect &
	ulimit -n 2048 && php fuzzing/server.php 8004 LibEv &
	wstest -m testeeserver -w ws://localhost:8000 &
	sleep 1
	wstest -m fuzzingclient -s fuzzing/all.json
	killall php wstest

stop:
	killall php wstest
