#!/usr/bin/env bash
# Configuration variables
PHP_MODULES_PATH="/usr/lib/php5/20090626"
REQUIRED_PACKAGES="php5-fpm php5-dev apache2-mpm-worker libapache2-mod-fastcgi nginx selinux-basics libselinux1-dev selinux-policy-dev gawk"
REQUIRED_APACHE_MODS="actions fastcgi"
SKIP_APACHE=0
SKIP_NGINX=0
SKIP_PHPSELINUX=0
SKIP_POLICY=0
ENABLE_JIT_AUTOGLOBALS=0

function usage {
	echo "Usage: $0 [--skip-apache] [--skip-nginx] [--skip-phpselinux] [--skip-policy] [--enable-jit-autoglobals]"
	exit 1
}

# Initialize variables
cwd=$( pwd )
ecwd=$( echo $cwd | sed 's/\//\\\//g' )

# Evaluate options
newopts=$( getopt -n"$0" --longoptions "skip-apache,skip-nginx,skip-phpselinux,skip-policy,enable-jit-autoglobals,help" "ansph" "$@" ) || usage
set -- $newopts
while (( $# >= 0 ))
do
	case "$1" in
		--skip-apache | -a)			SKIP_APACHE=1;shift;;
		--skip-nginx | -n)			SKIP_NGINX=1;shift;;
		--skip-phpselinux | -s)		SKIP_PHPSELINUX=1;shift;;
		--skip-policy | -p)			SKIP_POLICY=1;shift;;
		--enable-jit-autoglobals)	ENABLE_JIT_AUTOGLOBALS=1;shift;;
		--help | -h) usage;;
		--) shift;break;;
	esac
done

# Script must be run as root
if [[ $( whoami ) != "root" ]]
then
	echo "*** This script must be run as root" >&2 && exit 1
fi

# Check OS
if [[ ! -f /etc/debian_version || $( cat /etc/debian_version | cut -d. -f1 ) != "6" ]]
then
	echo "*** This script needs to be run on Debian Squeeze 6" >&2 && exit 1
fi

# Check packages
MISSING_PKGS=0
echo "Checking required packages ..."
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

# Check if SELinux is active
$( selinuxenabled )
if (( $? != 0 ))
then
	echo "*** SELinux not enabled. Please enable it by running selinux-activate ." >&2 && exit 1
fi

### Apache configuration ###
if (( $SKIP_APACHE == 0 ))
then
	# Check if required Apache modules are enabled
	echo -e "\nChecking if required Apache modules are enabled ..."
	for module in $REQUIRED_APACHE_MODS
	do
		if [[ -f "/etc/apache2/mods-enabled/$module.load" ]]
		then
			echo -e "\t[OK] $module"
		else
			echo -e "\t[DISABLED] $module"
			MISSING_MODS=1
		fi
	done
	if (( MISSING_MODS != 0 ))
	then
		echo "*** Some required Apache modules are disabled. Please enable them to continue." >&2 && exit 1
	fi
	
	# Include SePHP configuration
	echo -e "\nAdding SePHP Apache configuration ..."
	cp $cwd/configs/apache/conf.d/sephp.conf /etc/apache2/conf.d/ || exit 1

	# Disable default Apache virtualhost
	echo -e "Disabling default Apache virtualhost ..."
	a2dissite default

	# Copy virtualhost into apache sites and enable it
	echo -e "Enabling SePHP Apache virtualhost ..."
	cat "$cwd/configs/apache/sites-available/sephp-vhost.conf" | 
		sed 's/\${vhost_root}/'"$ecwd\/webroot"'/g' >/etc/apache2/sites-available/sephp-vhost.conf
	a2ensite sephp-vhost.conf || exit 1
	runcon $( cat /etc/selinux/default/contexts/initrc_context ) /etc/init.d/apache2 restart || exit 1
fi

### Nginx configuration ###
if (( $SKIP_NGINX == 0 ))
then
	# Disable default nginx virtualhost
	echo -e "\nDisabling default Nginx virtualhost ..."
	rm "/etc/nginx/sites-enabled/default" 2>/dev/null
	
	# Copy virtualhost into nginx sites and enable it
	echo -e "Enabling SePHP Nginx virtualhost ..."
	cat "$cwd/configs/nginx/sites-available/sephp-vhost.conf" | 
		sed 's/\${vhost_root}/'"$ecwd\/webroot"'/g' >/etc/nginx/sites-available/sephp-vhost.conf
	ln -s /etc/nginx/sites-available/sephp-vhost.conf /etc/nginx/sites-enabled/sephp-vhost.conf 2>/dev/null
	runcon $( cat /etc/selinux/default/contexts/initrc_context ) /etc/init.d/nginx restart || exit 1
fi

### policy module ###
if (( $SKIP_POLICY == 0 ))
then
	echo -e "\nBuilding FPM policy module ..."
	cd policy/php-fpm
	buildfail=0
	
	if (( buildfail == 0 )) ; then
		echo -e "\tExecuting make ..."
		make clean >/dev/null || buildfail=1
		make >/dev/null || buildfail=1
	fi

	if (( buildfail != 0 ))
	then
		echo "*** Build of FPM policy module failed." >&2 && exit 1
	fi
	
	echo -e "\tLoading policy module ..."
	semodule -i php-fpm.pp || exit 1
	cd $cwd
fi

### php5-selinux module ###
if (( $SKIP_PHPSELINUX == 0 ))
then
	echo -e "\nBuilding php5-selinux module ..."
	cd php5-selinux
	buildfail=0
	
	if (( buildfail == 0 )) ; then
		echo -e "\tExecuting phpize ..."
		phpize --clean >/dev/null || buildfail=1
		phpize >/dev/null || buildfail=1
	fi
	if (( buildfail == 0 )) ; then
		echo -e "\tExecuting configure ..."
		./configure >/dev/null || buildfail=1
	fi
	
	# Adding PHP_ADD_LIBRARY(selinux) in config.m4 in order to have libtool link 
	# with libselinux (-lselinux) seems not working. Perhaps there's an incompatibility between
	# phpize related tools and autotools in Debian 6.
	sed -i '1 i SELINUX_SHARED_LIBADD = -lselinux' Makefile
	
	if (( buildfail == 0 )) ; then 
		echo -e "\tExecuting make ..."
		make >/dev/null || buildfail=1
	fi
	
	cd $cwd
	if (( buildfail != 0 ))
	then
		echo "*** Build of php5-selinux module failed." >&2 && exit 1
	fi
	
	echo -e "\nLoading php5-selinux module ..."
	echo "extension=$cwd/php5-selinux/modules/selinux.so" > "/etc/php5/conf.d/selinux.ini" || exit 1
	if (( ENABLE_JIT_AUTOGLOBALS == 0 )) ; then
		echo "auto_globals_jit = Off" >> "/etc/php5/conf.d/selinux.ini" || exit 1
	fi
	runcon $( cat /etc/selinux/default/contexts/initrc_context ) /etc/init.d/php5-fpm restart || exit 1
fi
