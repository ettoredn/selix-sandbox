#!/usr/bin/env bash
# Configuration variables
DB_USER="root"
DB_PASS="ettore"
DB_DATABASE="php_benchmark"
DB_TABLE_SESSION="session"
DB_TABLE_TRACEDATA="tracedata"
SELIX_INI="/etc/php5/conf.d/selix.ini"
PASSTHROUGH_INI="/etc/php5/conf.d/passthrough.ini"
TRACES_DIR="/dev/shm/traces"
SQL_TMP_PATH="/dev/shm"
BABELTRACE_ARGS="--clock-seconds -n header,args -f loglevel"
# How many times each benchmark is executed and data collected
BENCHMARK_COUNT="10"
# NICENESS="0"
ENV_ARGS="LD_PRELOAD=liblttng-ust-fork.so\
 SELINUX_DOMAIN=sephp_php_t\
 SELINUX_RANGE=s0\
 SELINUX_COMPILE_DOMAIN=sephp_php_t\
 SELINUX_COMPILE_RANGE=s0"

function usage {
	echo "Usage: $0 [--count=N] [--description=\"text\"]"
	exit 1
}

function quit {
	cd "$old_cwd"
	if (( $1 > 0 )) ; then echo -e "\n*** Aborted due to previous errors" ; fi
	exit $1
}

function enable_selix {
	# echo "Enabling selix extension ..."
	sudo sed -i 's/^;\?.*zend_extension\s*=\s*\(.*\)selix\.so$/zend_extension=\1selix.so/' "$SELIX_INI"
}
function disable_selix {
	# echo "Disabling selix extension ..."
	sudo sed -i 's/^;\?.*zend_extension\s*=\s*\(.*\)selix\.so$/;zend_extension=\1selix.so/' "$SELIX_INI"
}
function enable_passthrough {
	# echo "Enabling passthrough extension ..."
	sudo sed -i 's/^;\?.*zend_extension\s*=\s*\(.*\)passthrough\.so$/zend_extension=\1passthrough.so/' "$PASSTHROUGH_INI"
}
function disable_passthrough {
	# echo "Disabling passthrough extension ..."
	sudo sed -i 's/^;\?.*zend_extension\s*=\s*\(.*\)passthrough\.so$/;zend_extension=\1passthrough.so/' "$PASSTHROUGH_INI"
}

function run_benchmarks {
	for testfile in $run_tests
	do
		echo -en "\t$testfile "
		for (( i=0; i<$BENCHMARK_COUNT; i++ ))
		do
			# cmd="$NICE_ARGS env $ENV_ARGS php $testfile"
			cmd="env $ENV_ARGS php $testfile"
			# echo $cmd # $NICE_ARGS env $ENV_ARGS env 
			$cmd >/dev/null || quit 1
			echo -en "."
		done
		echo
	done
}

# Must be run inside a while loop feeded with babeltrace output
# $1 is the configuration name
function parse_tracedata {
	config="$1"
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
		VALUES( $lttng_session, '$config', $timestamp, $delta, $level, '$name', '$args' );"
	echo $sql >> "$sqltmpfile"
}

# Initialize variables
old_cwd=$( pwd )
abspath=$(cd ${0%/*} && echo $PWD/${0##*/})
cwd=$( dirname "$abspath" )
ecwd=$( echo $cwd | sed 's/\//\\\//g' )
lttng_session=$( date +%s )
description=""

# Evaluate options
newopts=$( getopt -n"$0" --longoptions "count:,description:,help" "h" "$@" ) || usage
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
		--description)	description=$( echo $@ | sed "s/--description '\([[:print:]]\+\)' --/\1/" )
					set -- $( echo $@ | sed "s/'\([[:print:]]*\)'//" )
					shift;;
		# --niceness)	NICENESS=$( echo $2 | sed "s/'//g" )
		# 			NICE_ARGS="nice -n $NICENESS"
		# 			
		# 			if (( NICENESS < 0 ))
		# 			then
		# 				sudo echo >/dev/null || ( echo "*** You must be able to sudo without specifying a password" >&2 && quit 1 )
		# 				NICE_ARGS="sudo $NICE_ARGS"
		# 			fi
		# 			shift;shift;;
		--help | -h) usage;;
		--) shift;break;;
	esac
done

# Change to script directory
cd "$cwd"

# Creates a list with tests to run
for testfile in *.php
do
	run_tests="$run_tests $testfile"
done

# SELinux should be in permissive mode
if [[ $( cat /selinux/enforce ) != 0 ]]
then
	echo "*** SELinux must be in permissive mode" >&2 && quit 1
fi

# Check LTTng v2
if [[ $( lttng -h | head -1 | cut -d" " -f4 | cut -d. -f1 ) != 2 ]]
then
	echo "*** LTTng-2.0 is needed to trace PHP" >&2 && quit 1
fi

# Check php-cli
which php &>/dev/null || ( echo "*** Can't locate php-cli" >&2 && quit 1 )

# selix extension must be disabled
if (( $( php -m | egrep '^selix$' | wc -l ) > 0 ))
then
	# selix not loaded
	[ -f "$SELIX_INI" ] || ( echo "*** $SELIX_INI must be present" >&2 && quit 1 )
	
	# the extension load directive must be present
	egrep 'zend_extension.*selix.so$' "$SELIX_INI" &>/dev/null || ( echo "*** Extension load directive must be present in $SELIX_INI" >&2 && quit 1 )
	
	# Disable selix
	echo "selix extension detected as enabled. Disabling ..."
	disable_selix
fi

# passthrough extension must be disabled
if (( $( php -m | egrep '^passthrough$' | wc -l ) > 0 ))
then
	# passthrough not loaded
	[ -f "$PASSTHROUGH_INI" ] || ( echo "*** $PASSTHROUGH_INI must be present" >&2 && quit 1 )
	
	# the extension load directive must be present
	egrep 'zend_extension.*passthrough.so$' "$PASSTHROUGH_INI" &>/dev/null || ( echo "*** Extension load directive must be present in $PASSTHROUGH_INI" >&2 && quit 1 )
	
	# Disable passthrough
	echo "passthrough extension detected as enabled. Disabling ..."
	disable_passthrough
fi

### Run benchmarks with php basic configuration (selix/passthrough disabled) ###
echo -e "\nRunning benchmarks $BENCHMARK_COUNT times with php ..."
tracepath="$TRACES_DIR/php"
rm -rf "$tracepath" && mkdir -p "$tracepath" || quit 1
# Create LTTng session
lttng create --output "$tracepath" "$lttng_session" >/dev/null || quit 1
lttng enable-event "PHP_PHP:*" -u --tracepoint >/dev/null || quit 1
lttng enable-event "PHP_Zend:*" -u --tracepoint >/dev/null || quit 1
lttng start "$lttng_session" >/dev/null || quit 1
# Run benchmarks
run_benchmarks
# Destroy LTTng session
lttng stop "$lttng_session" >/dev/null || quit 1
lttng destroy "$lttng_session" >/dev/null || quit 1

### Run benchmarks with passthrough extension enabled ###
echo -e "\nRunning benchmarks $BENCHMARK_COUNT times in passthrough configuration ..."
enable_passthrough
tracepath="$TRACES_DIR/passthrough"
rm -rf "$tracepath" && mkdir -p "$tracepath" || quit 1
# Create LTTng session
lttng create --output "$tracepath" "$lttng_session" >/dev/null || quit 1
lttng enable-event "PHP_PHP:*" -u --tracepoint >/dev/null || quit 1
lttng enable-event "PHP_Zend:*" -u --tracepoint >/dev/null || quit 1
lttng start "$lttng_session" >/dev/null || quit 1
# Run benchmarks
run_benchmarks
# Destroy LTTng session
lttng stop "$lttng_session" >/dev/null || quit 1
lttng destroy "$lttng_session" >/dev/null || quit 1
disable_passthrough

### Run benchmarks with selix extension enabled ###
echo -e "\nRunning benchmarks $BENCHMARK_COUNT times with selix enabled ..."
enable_selix
tracepath="$TRACES_DIR/selix"
rm -rf "$tracepath" && mkdir -p "$tracepath" || quit 1
# Create LTTng session
lttng create --output "$tracepath" "$lttng_session" >/dev/null || quit 1
lttng enable-event "PHP_PHP:*" -u --tracepoint >/dev/null || quit 1
lttng enable-event "PHP_Zend:*" -u --tracepoint >/dev/null || quit 1
lttng start "$lttng_session" >/dev/null || quit 1
# Run benchmarks
run_benchmarks
# Destroy LTTng session
lttng stop "$lttng_session" >/dev/null || quit 1
lttng destroy "$lttng_session" >/dev/null || quit 1


### Load trace data into the database ###
echo -e "\nLoading trace data into database ..."
sqltmpfile="$SQL_TMP_PATH/$lttng_session.sql"
echo "START TRANSACTION;" > "$sqltmpfile"

while read line
do
	parse_tracedata "php"
done <<< "$( babeltrace $BABELTRACE_ARGS "$TRACES_DIR/php" )"

while read line
do
	parse_tracedata "passthrough"
done <<< "$( babeltrace $BABELTRACE_ARGS "$TRACES_DIR/passthrough" )"

while read line
do
	parse_tracedata "selix"
done <<< "$( babeltrace $BABELTRACE_ARGS "$TRACES_DIR/selix" )"

# Session info
run_tests=$( echo ${run_tests:1} | sed 's/\.php//g' )
echo "INSERT INTO $DB_TABLE_SESSION (session, benchmarks, runs, description) \
	VALUES( $lttng_session, '$run_tests', $BENCHMARK_COUNT, '$description' );" >> "$sqltmpfile"

echo "COMMIT;" >> "$sqltmpfile"
mysql -u "$DB_USER" -p"$DB_PASS" -D "$DB_DATABASE" < "$sqltmpfile" || quit 1
rm "$sqltmpfile"

quit 0
