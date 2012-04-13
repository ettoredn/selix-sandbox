#!/usr/bin/env bash
TRACES_DIR="/dev/shm/traces"
BABELTRACE_ARGS="--clock-seconds"
ENV_ARGS="LD_PRELOAD=liblttng-ust-fork.so\
 SELINUX_DOMAIN=sephp_php_t\
 SELINUX_RANGE=s0\
 SELINUX_COMPILE_DOMAIN=sephp_php_t\
 SELINUX_COMPILE_RANGE=s0"

function usage {
	echo "Usage: $0 [--selix] [filename]"
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
lttng_session=$( date +%s )
tracepath="$TRACES_DIR/cli"
event_filter="PHP_*"

# Evaluate options
newopts=$( getopt -n"$0" --longoptions "selix,help" "sh" "$@" ) || usage
set -- $newopts
while (( $# >= 0 ))
do
	case "$1" in
		--selix)	event_filter="PHP_selix:*";shift;;
		--help | -h) usage;;
		--) shift;break;;
	esac
done

scriptname="$( echo $1 | sed "s/'//g" )"

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

# Create LTTng session
rm -rf "$tracepath" && mkdir -p "$tracepath" || quit 1
lttng create --output "$tracepath" "$lttng_session" >/dev/null || quit 1
lttng enable-event "$event_filter" -u --tracepoint >/dev/null || quit 1
lttng start "$lttng_session" >/dev/null || quit 1
# Run PHP
cmd="env $ENV_ARGS php $scriptname"
$cmd >/dev/null
# Destroy LTTng session
lttng stop "$lttng_session" >/dev/null || quit 1
lttng destroy "$lttng_session" >/dev/null || quit 1

# Display trace
babeltrace $BABELTRACE_ARGS "$TRACES_DIR/cli"

#lttng enable-event "PHP_selix:*" -u