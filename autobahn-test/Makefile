
# StreamSelectLoop - port 8001
# ExtEvLoop        - port 8002
# AutobahnPython   - port 8010

stream_select:
	ulimit -n 2048 && php fuzzing/server.php 8001 StreamSelectLoop &
	sleep 1
	wstest -m fuzzingclient -s fuzzing/stream_select.json
	killall php

ext_ev:
	ulimit -n 2048 && php fuzzing/server.php 8002 ExtEvLoop &
	sleep 1
	wstest -m fuzzingclient -s fuzzing/ext_ev.json
	killall php

complete:
	ulimit -n 2048 && php fuzzing/server.php 8001 StreamSelectLoop &
	ulimit -n 2048 && php fuzzing/server.php 8002 ExtEvLoop &
	wstest -m testeeserver -w ws://localhost:8010 &
	sleep 1
	wstest -m fuzzingclient -s fuzzing/complete.json
	killall php wstest

stop:
	killall php wstest
