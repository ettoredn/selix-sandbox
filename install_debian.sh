#!/usr/bin/env bash
REQUIRED_PACKAGES="php5-fpm apache2-mpm-worker selinux-basics"

# Check OS
if [[ ! -f /etc/debian_version || $( cat /etc/debian_version | cut -d. -f1 ) != "6" ]]
then
	echo -e "*** This scripts need to be run on Debian Squeeze 6" >&2 && exit 1
fi

MISSING_PKGS=0
echo "Checking required packages.."
for package in $REQUIRED_PACKAGES
do
	if [[ $( dpkg -s $package 2>/dev/null | egrep Status | cut -d" " -f4 ) == "installed" ]]
	then
		echo -e "\t[OK] $package"
	else
		echo -e "\t[MISSING] $package"
		MISSING_PKGS=1
	fi
done
if (( MISSING_PKGS != 0 ))
then
	echo "*** Packages missing. Please install them to continue." >&2 && exit 1
fi
