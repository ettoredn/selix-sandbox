#!/usr/bin/env bash
TRACES_DIR="/dev/shm/traces"
BABELTRACE_ARGS="--clock-seconds"

function usage {
	echo "Usage: $0"
	exit 1
}

function quit {
	cd "$old_cwd"
	if (( $1 > 0 )) ; then echo -e "\n*** Aborted due to previous errors" ; fi
	exit $1
}

# Initialize variables
old_cwd=$( pwd )
abspath=$(cd ${0%/*} && echo $PWD/${0##*/})
cwd=$( dirname "$abspath" )
ecwd=$( echo $cwd | sed 's/\//\\\//g' )
lttng_session="php-fpm"
tracepath="$TRACES_DIR/fpm"

# Evaluate options
newopts=$( getopt -n"$0" --longoptions "help" "sh" "$@" ) || usage
set -- $newopts
while (( $# >= 0 ))
do
	case "$1" in
		--help | -h) usage;;
		--) shift;break;;
	esac
done

# Script must be run as root
if [[ $( whoami ) != "root" ]]
then
	echo "*** This script must be run as root" >&2 && quit 1
fi

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

# Kill all existing instances
killall --signal 9 php-fpm || quit 1

# Destroy LTTng session
lttng stop "$lttng_session" >/dev/null || quit 1
lttng destroy "$lttng_session" >/dev/null || quit 1

# Display trace
babeltrace $BABELTRACE_ARGS "$tracepath"

quit 0