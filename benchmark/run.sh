#!/usr/bin/env bash
# Configuration variables
DB_USER="selix"
DB_PASS="ettore"
DB_DATABASE="tracedata"
DB_TABLE_SELIX="selix"
DB_TABLE_PHP="php"
SELIX_INI="/etc/php5/conf.d/selix.ini"
TRACES_DIR="traces"
BABELTRACE_ARGS="--clock-seconds -n header,args -f loglevel"
# How many times each benchmark is executed and data collected
BENCHMARK_TIMES=10

function usage {
	echo "Usage: $0"
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

# Evaluate options
newopts=$( getopt -n"$0" --longoptions "help" "h" "$@" ) || usage
set -- $newopts
while (( $# >= 0 ))
do
	case "$1" in
#		--skip-apache | -a)			SKIP_APACHE=1;shift;;
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
echo -e "\nRunning benchmarks $BENCHMARK_TIMES times with selix enabled ..."
tracepath="$TRACES_DIR/selix"
rm -rf "$tracepath" && mkdir -p "$tracepath" || quit 1

# Create LTTng session
lttng create --output "$tracepath" "$lttng_session" >/dev/null || quit 1
lttng enable-event -a -u >/dev/null || quit 1
lttng start "$lttng_session" >/dev/null || quit 1

for testfile in *.php
do
	echo -en "\t$testfile "
	for (( i=0; i<$BENCHMARK_TIMES; i++ ))
	do
		env LD_PRELOAD="liblttng-ust-fork.so" php "$testfile" >/dev/null || quit 1
		echo -en "."
	done
	echo
done

# Destroy LTTng session
lttng stop "$lttng_session" >/dev/null || quit 1
lttng destroy "$lttng_session" >/dev/null || quit 1

### Run benchmarks with selix extension disabled ###
echo -e "\nRunning benchmarks $BENCHMARK_TIMES times with selix disabled ..."
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
	for (( i=0; i<$BENCHMARK_TIMES; i++ ))
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
	echo $sql | mysql -u "$DB_USER" -p"$DB_PASS" -D "$DB_DATABASE" || quit 1
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
	echo $sql | mysql -u "$DB_USER" -p"$DB_PASS" -D "$DB_DATABASE" || quit 1
done <<< "$( babeltrace $BABELTRACE_ARGS "$TRACES_DIR/php" )"

quit 0
# A PHP script into webroot generates fancy graphs
# http://people.iola.dk/olau/flot/examples/stacking.html
# http://pchart.sourceforge.net/screenshots.php?ID=8
