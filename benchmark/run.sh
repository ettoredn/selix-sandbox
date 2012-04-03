#!/usr/bin/env bash
# Configuration variables
DB_USER="selix"
DB_PASS="ettore"
DB_DATABASE="tracedata"
DB_TABLE_SELIX="selix"
DB_TABLE_PHP="php"
DB_TABLE_SESSION="session"
SELIX_INI="/etc/php5/conf.d/selix.ini"
TRACES_DIR="traces"
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
sqlfile="$lttng_session.sql"
echo "START TRANSACTION;" > "$sqlfile"
while read line
do
	timestamp=$( echo $line | cut -d, -f1 | cut -d" " -f3 ) #sec_epoch.ns
	delta=$( echo $line | cut -d, -f2 | cut -d" " -f4 | cut -d+ -f2 ) #sec.ns
	level=$( echo $line | cut -d, -f3 | cut -d" " -f5 | sed 's/^(\([0-9]\+\))$/\1/' )
	name=$( echo $line | cut -d, -f4 | cut -d" " -f4 )
	args=$( echo $line | cut -d, -f6 | cut -d{ -f2 | sed 's/^ \(.*\) }$/\1/' )
	
	if [[ $delta == "?.?????????" ]] ; then delta="NULL" ; fi
	
	sql="INSERT INTO $DB_TABLE_SELIX (session, timestamp, delta, loglevel, name, args) \
		VALUES( $lttng_session, $timestamp, $delta, $level, '$name', '$args' );"
	echo $sql >> "$sqlfile"
done <<< "$( babeltrace $BABELTRACE_ARGS "$TRACES_DIR/selix" )"

while read line
do
	timestamp=$( echo $line | cut -d, -f1 | cut -d" " -f3 ) #sec_epoch.ns
	delta=$( echo $line | cut -d, -f2 | cut -d" " -f4 | cut -d+ -f2 ) #sec.ns
	level=$( echo $line | cut -d, -f3 | cut -d" " -f5 | sed 's/^(\([0-9]\+\))$/\1/' )
	name=$( echo $line | cut -d, -f4 | cut -d" " -f4 )
	args=$( echo $line | cut -d, -f6 | cut -d{ -f2 | sed 's/^ \(.*\) }$/\1/' )
	
	if [[ $delta == "?.?????????" ]] ; then delta="NULL" ; fi
	
	sql="INSERT INTO $DB_TABLE_PHP (session, timestamp, delta, loglevel, name, args) \
		VALUES( $lttng_session, $timestamp, $delta, $level, '$name', '$args' );"
	echo $sql >> "$sqlfile"
done <<< "$( babeltrace $BABELTRACE_ARGS "$TRACES_DIR/php" )"

# Session info
run_tests=$( echo ${run_tests:1} | sed 's/\.php//g' )
echo "INSERT INTO $DB_TABLE_SESSION (session, benchmarks) \
	VALUES( $lttng_session, '$run_tests' );" >> "$sqlfile"

echo "COMMIT;" >> "$sqlfile"
mysql -u "$DB_USER" -p"$DB_PASS" -D "$DB_DATABASE" < "$sqlfile" || quit 1
rm "$sqlfile"

enable_selix
quit 0
# A PHP script into webroot generates fancy graphs
# http://people.iola.dk/olau/flot/examples/stacking.html
# http://pchart.sourceforge.net/screenshots.php?ID=8
