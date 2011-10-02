#!/usr/bin/env bash
# Configuration variables
REQUIRED_PACKAGES="php5-fpm php5-dev apache2-mpm-worker apache2-threaded-dev libapache2-mod-fastcgi nginx selinux-basics libselinux1-dev selinux-policy-dev gawk"
REQUIRED_APACHE_MODS="actions fastcgi"
SKIP_APACHE=0
SKIP_NGINX=0
SKIP_SELIX=0
SKIP_POLICY=0
SKIP_MOD_SELINUX=0
PHP_ENABLE_JIT_AUTOGLOBALS=0
SELIX_FORCE_CONTEXT_CHANGE=0
SELIX_VERBOSE=1

function usage {
	echo "Usage: $0 [--skip-apache|-a] [--skip-nginx|-n] [--skip-selix|-s] [--skip-policy|-p] [--skip-modselinux|-m] [--force-context-change] [--enable-jit-autoglobals] [--disable-verbose]"
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
restart_apache=0
restart_php=0
restart_nginx=0

# Evaluate options
newopts=$( getopt -n"$0" --longoptions "skip-apache,skip-nginx,skip-selix,skip-policy,skip-modselinux,force-context-change,enable-jit-autoglobals,disable-verbose,help" "anspmh" "$@" ) || usage
set -- $newopts
while (( $# >= 0 ))
do
	case "$1" in
		--skip-apache | -a)			SKIP_APACHE=1;shift;;
		--skip-nginx | -n)			SKIP_NGINX=1;shift;;
		--skip-selix | -s)			SKIP_SELIX=1;shift;;
		--skip-policy | -p)			SKIP_POLICY=1;shift;;
		--skip-modselinux | -m)		SKIP_MOD_SELINUX=1;shift;;
		--enable-jit-autoglobals)	PHP_ENABLE_JIT_AUTOGLOBALS=1;shift;;
		--force-context-change)		SELIX_FORCE_CONTEXT_CHANGE=1;shift;;
		--disable-verbose)			SELIX_VERBOSE=0;shift;;
		--help | -h) usage;;
		--) shift;break;;
	esac
done

# Change to script directory
cd "$cwd"

# Script must be run as root
if [[ $( whoami ) != "root" ]]
then
	echo "*** This script must be run as root" >&2 && quit 1
fi

# Check OS
if [[ ! -f /etc/debian_version || $( cat /etc/debian_version | cut -d. -f1 ) != "6" ]]
then
	echo "*** This script needs to be run on Debian Squeeze 6" >&2 && quit 1
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
	echo "*** Packages missing. Please install them to continue." >&2 && quit 1
fi

# Check if SELinux is active
$( selinuxenabled )
if (( $? != 0 ))
then
	echo "*** SELinux not enabled. Please enable it by running selinux-activate ." >&2 && quit 1
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
		echo "*** Some required Apache modules are disabled. Please enable them to continue." >&2 && quit 1
	fi
	
	echo -e "\nUpdating Apache configuration ..."
	# Include SePHP configuration
	echo -e "\tAdding SePHP configuration ..."
	cp $cwd/configs/apache/conf.d/sephp.conf /etc/apache2/conf.d/ || quit 1

	# Disable default Apache virtualhost
	echo -e "\tDisabling default Apache virtualhost ..."
	a2dissite default >/dev/null

	# Copy virtualhost into apache sites and enable it
	echo -e "\tEnabling SePHP Apache virtualhost ..."
	cat "$cwd/configs/apache/sites-available/sephp-vhost.conf" | 
		sed 's/\${vhost_root}/'"$ecwd\/webroot"'/g' >/etc/apache2/sites-available/sephp-vhost.conf
	a2ensite sephp-vhost.conf >/dev/null || quit 1
	restart_apache=1
fi

### Nginx configuration ###
if (( $SKIP_NGINX == 0 ))
then
	echo -e "\nUpdating Nginx configuration ..."
	
	# Disable default nginx virtualhost
	echo -e "\tDisabling default Nginx virtualhost ..."
	rm "/etc/nginx/sites-enabled/default" 2>/dev/null
	
	# Copy virtualhost into nginx sites and enable it
	echo -e "\tEnabling SePHP Nginx virtualhost ..."
	cat "$cwd/configs/nginx/sites-available/sephp-vhost.conf" | 
		sed 's/\${vhost_root}/'"$ecwd\/webroot"'/g' >/etc/nginx/sites-available/sephp-vhost.conf
	ln -s /etc/nginx/sites-available/sephp-vhost.conf /etc/nginx/sites-enabled/sephp-vhost.conf 2>/dev/null
	restart_nginx=1
fi

### policy module ###
if (( $SKIP_POLICY == 0 ))
then
	echo -e "\nBuilding mod_selinux and FPM policy modules ..."
	cd policy || quit 1
	buildfail=0

	if (( buildfail == 0 )) ; then
		echo -e "\tExecuting make ..."
		make clean >/dev/null || buildfail=1
		make >/dev/null || buildfail=1
	fi

	if (( buildfail != 0 ))
	then
		echo "*** Build of policy modules failed." >&2 && quit 1
	fi

	echo -e "\tLoading mod_selinux policy module ..."
	semodule -r mod_selinux &>/dev/null
	semodule -i mod_selinux.pp >/dev/null || quit 1
	echo -e "\tLoading PHP-FPM policy module ..."
	semodule -r php-fpm &>/dev/null
	semodule -i php-fpm.pp >/dev/null || quit 1
	cd $cwd
fi

### mod_selinux module ###
if (( $SKIP_MOD_SELINUX == 0 ))
then
	# Check if required Apache modules are enabled
	echo -e "\nBuilding mod_selinux ..."
	cd mod_selinux
	buildfail=0
	
	if (( buildfail == 0 )) ; then
		echo -e "\tExecuting make ..."
		make clean >/dev/null || buildfail=1
		make >/dev/null || buildfail=1
	fi
	if (( buildfail == 0 )) ; then
		make install >/dev/null || buildfail=1
	fi
		
	cd $cwd
	if (( buildfail != 0 ))
	then
		echo "*** Build of mod_selinux module failed." >&2 && quit 1
	fi
	
	# Include mod_selinux configuration
	echo -e "\tAdding mod_selinux Apache configuration ..."
	cp $cwd/configs/apache/mods-available/mod_selinux.* /etc/apache2/mods-available/ || quit 1

	a2enmod mod_selinux >/dev/null || quit 1
	restart_apache=1
fi

### php5-selinux module ###
if (( $SKIP_SELIX == 0 ))
then
	echo -e "\nBuilding selix PHP extension ..."
	
	if [[ ! -d selix ]] ; then
		echo "*** You need to clone selix project into a directory named selix" >&2 && quit 1
	fi
	cd selix
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
	sed -i '1 i SELIX_SHARED_LIBADD = -lselinux' Makefile
	
	if (( buildfail == 0 )) ; then 
		echo -e "\tExecuting make ..."
		make >/dev/null || buildfail=1
	fi
	
	cd $cwd
	if (( buildfail != 0 ))
	then
		echo "*** Build of php5-selinux module failed." >&2 && quit 1
	fi
	
	echo -e "\tLoading selix extension ..."
	echo "extension=$cwd/selix/modules/selix.so" > "/etc/php5/conf.d/selix.ini" || quit 1
	if (( PHP_ENABLE_JIT_AUTOGLOBALS == 0 )) ; then
		echo "auto_globals_jit = Off" >> "/etc/php5/conf.d/selix.ini" || quit 1
	fi
	if (( SELIX_FORCE_CONTEXT_CHANGE == 1 )) ; then
		echo "selix.force_context_change = On" >> "/etc/php5/conf.d/selix.ini" || quit 1
	fi
	if (( SELIX_VERBOSE == 1 )) ; then
		echo "selix.verbose = On" >> "/etc/php5/conf.d/selix.ini" || quit 1
	fi
	restart_php=1
fi

# Restart services if needed
echo -e ""
if (( restart_apache == 1 )) ; then
	runcon $( cat /etc/selinux/default/contexts/initrc_context ) /etc/init.d/apache2 restart || quit 1
fi
if (( restart_php == 1 )) ; then
	runcon $( cat /etc/selinux/default/contexts/initrc_context ) /etc/init.d/php5-fpm restart || quit 1
fi
if (( restart_nginx == 1 )) ; then
	runcon $( cat /etc/selinux/default/contexts/initrc_context ) /etc/init.d/nginx restart || quit 1
fi

quit 0