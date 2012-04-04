#!/usr/bin/env bash
# Configuration variables
DB_USER="selix"
DB_PASS="ettore"
DB_DATABASE="php_benchmark"
DB_TABLE_SESSION="session"
DB_TABLE_TRACEDATA="tracedata"
SELIX_INI="/etc/php5/conf.d/selix.ini"
TRACES_DIR="/dev/shm/traces"
SQL_TMP_PATH="/dev/shm"
BABELTRACE_ARGS="--clock-seconds -n header,args -f loglevel"
# How many times each benchmark is executed and data collected
BENCHMARK_COUNT="10"

function usage {
	echo "Usage: $0 [--count=N]"
	exit 1
}

function quit {
	cd "$old_cwd"
	if (( $1 > 0 )) ; then echo -e "\n*** Aborted due to previous errors" ; fi
	exit $1
}

function enable_selix {
	# echo "Enabling selix extension ..."
	sudo sed -i 's/^;\?.*extension\s*=\s*\(.*\)selix\.so$/extension=\1selix.so/' "$SELIX_INI"
}

function disable_selix {
	# echo "Disabling selix extension ..."
	sudo sed -i 's/^;\?.*extension\s*=\s*\(.*\)selix\.so$/;extension=\1selix.so/' "$SELIX_INI"
}

# Initialize variables
old_cwd=$( pwd )
abspath=$(cd ${0%/*} && echo $PWD/${0##*/})
cwd=$( dirname "$abspath" )
ecwd=$( echo $cwd | sed 's/\//\\\//g' )
lttng_session=$( date +%s )
run_tests=""

# Evaluate options
newopts=$( getopt -n"$0" --longoptions "count:,help" "h" "$@" ) || usage
set -- $newopts
while (( $# >= 0 ))
do
	case "$1" in
		--count)	BENCHMARK_COUNT=$( echo $2 | sed "s/'//g" )
					if (( BENCHMARK_COUNT < 1 ))
					then
						echo "*** count argument must be > 0" >&2 && quit 1
					fi
					shift;shift;;
		--help | -h) usage;;
		--) shift;break;;
	esac
done

# Change to script directory
cd "$cwd"

# SELinux should be disabled
if [[ $( cat /selinux/enforce ) != 0 ]]
then
	echo "*** SELinux must be disabled" >&2 && quit 1
fi

# Check LTTng v2
if [[ $( lttng -h | head -1 | cut -d" " -f4 | cut -d. -f1 ) != 2 ]]
then
	echo "*** LTTng-2.0 is needed to trace PHP" >&2 && quit 1
fi

# Check php-cli
which php &>/dev/null || ( echo "*** Can't locate php-cli" >&2 && quit 1 )

# selix extension must be enabled
if [[ $( php -m | egrep '^selix$' ) != "selix" ]]
then
	# selix not loaded
	[ -f "$SELIX_INI" ] || ( echo "*** $SELIX_INI must be present" >&2 && quit 1 )
	
	# the extension load directive must be present
	egrep 'extension.*selix.so$' "$SELIX_INI" &>/dev/null || ( echo "*** Extension load directive must be present in $SELIX_INI" >&2 && quit 1 )
	
	# Enable selix
	echo "selix extension detected as disabled. Enabling ..."
	enable_selix
fi

### Run benchmarks with selix extension enabled ###
echo -e "\nRunning benchmarks $BENCHMARK_COUNT times with selix enabled ..."
tracepath="$TRACES_DIR/selix"
rm -rf "$tracepath" && mkdir -p "$tracepath" || quit 1

# Create LTTng session
lttng create --output "$tracepath" "$lttng_session" >/dev/null || quit 1
lttng enable-event -a -u >/dev/null || quit 1
lttng start "$lttng_session" >/dev/null || quit 1

for testfile in *.php
do
	echo -en "\t$testfile "
	for (( i=0; i<$BENCHMARK_COUNT; i++ ))
	do
		env LD_PRELOAD="liblttng-ust-fork.so" php "$testfile" >/dev/null || quit 1
		echo -en "."
	done
	
	run_tests="$run_tests $testfile"
	echo
done

# Destroy LTTng session
lttng stop "$lttng_session" >/dev/null || quit 1
lttng destroy "$lttng_session" >/dev/null || quit 1

### Run benchmarks with selix extension disabled ###
echo -e "\nRunning benchmarks $BENCHMARK_COUNT times with selix disabled ..."
disable_selix
tracepath="$TRACES_DIR/php"
rm -rf "$tracepath" && mkdir -p "$tracepath" || quit 1

# Create LTTng session
lttng create --output "$tracepath" "$lttng_session" >/dev/null || quit 1
lttng enable-event -a -u >/dev/null || quit 1
lttng start "$lttng_session" >/dev/null || quit 1

for testfile in *.php
do
	echo -en "\t$testfile "
	for (( i=0; i<$BENCHMARK_COUNT; i++ ))
	do
		env LD_PRELOAD="liblttng-ust-fork.so" php "$testfile" >/dev/null || quit 1
		echo -en "."
	done
	echo
done

# Destroy LTTng session
lttng stop "$lttng_session" >/dev/null || quit 1
lttng destroy "$lttng_session" >/dev/null || quit 1

### Load trace data into the database ###
echo -e "\nLoading trace data into database ..."
sqltmpfile="$SQL_TMP_PATH/$lttng_session.sql"
echo "START TRANSACTION;" > "$sqltmpfile"
while read line
do
	seded=$( echo $line | \
		sed 's/^timestamp = \([[:digit:].]\{20,\}\), delta = +\([[:digit:].?]\{11,\}\), loglevel = [A-Z_]\+ (\([[:digit:]]\+\)), name = \([[:graph:]]\+\), .\+, { \([[:print:]]\+\) }$/\1|\2|\3|\4|\5/' \
		)
	# BUG: if last token (i.e args) contains '|' it gets replaced with ' '
	IFS='|'; tokens=( $seded ); IFS=' '
	timestamp="${tokens[0]}"
	delta="${tokens[1]}"
	level="${tokens[2]}"
	name="${tokens[3]}"
	args="${tokens[@]:4}"

	if [[ $delta == "?.?????????" ]] ; then delta="NULL" ; fi
	
	sql="INSERT INTO $DB_TABLE_TRACEDATA \
		(session, configuration, timestamp, delta, loglevel, name, args) \
		VALUES( $lttng_session, 'selix', $timestamp, $delta, $level, '$name', '$args' );"
	echo $sql >> "$sqltmpfile"
done <<< "$( babeltrace $BABELTRACE_ARGS "$TRACES_DIR/selix" )"

while read line
do
	seded=$( echo $line | \
		sed 's/^timestamp = \([[:digit:].]\{20,\}\), delta = +\([[:digit:].?]\{11,\}\), loglevel = [A-Z_]\+ (\([[:digit:]]\+\)), name = \([[:graph:]]\+\), .\+, { \([[:print:]]\+\) }$/\1|\2|\3|\4|\5/' \
		)
	# BUG: if last token (i.e args) contains '|' it gets replaced with ' '
	IFS='|'; tokens=( $seded ); IFS=' '
	timestamp="${tokens[0]}"
	delta="${tokens[1]}"
	level="${tokens[2]}"
	name="${tokens[3]}"
	args="${tokens[@]:4}"
		
	if [[ $delta == "?.?????????" ]] ; then delta="NULL" ; fi
	
	sql="INSERT INTO $DB_TABLE_TRACEDATA \
		(session, configuration, timestamp, delta, loglevel, name, args) \
		VALUES( $lttng_session, 'php', $timestamp, $delta, $level, '$name', '$args' );"
	echo $sql >> "$sqltmpfile"
done <<< "$( babeltrace $BABELTRACE_ARGS "$TRACES_DIR/php" )"

# Session info
run_tests=$( echo ${run_tests:1} | sed 's/\.php//g' )
echo "INSERT INTO $DB_TABLE_SESSION (session, benchmarks) \
	VALUES( $lttng_session, '$run_tests' );" >> "$sqltmpfile"

echo "COMMIT;" >> "$sqltmpfile"
mysql -u "$DB_USER" -p"$DB_PASS" -D "$DB_DATABASE" < "$sqltmpfile" || quit 1
rm "$sqltmpfile"

enable_selix
quit 0
