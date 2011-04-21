#!/usr/bin/env bash
# Configuration variables
REQUIRED_PACKAGES="php5-fpm apache2-mpm-worker selinux-basics libapache2-mod-fastcgi"
REQUIRED_APACHE_MODS="actions fastcgi"
DISABLE_APACHE=0
DISABLE_NGINX=1

function usage {
	echo "Usage: $0 [--disable-apache] [--disable-nginx]"
	exit 1
}

# Initialize variables
cwd=$( pwd )
ecwd=$( echo $cwd | sed 's/\//\\\//g' )

# Evaluate options
newopts=$( getopt -n$0 -a --longoptions="disable-apache disable-nginx" "h" "$@" ) || usage
set -- $newopts

while (( $# > 0 ))
do
    case "$1" in
       --disable-apache)   DISABLE_APACHE=1;shift;;
       --disable-nginx)   DISABLE_NGINX=1;shift;;
       -h)        usage;;
       --)        shift;break;;
       -*)        usage;;
       *)         break;;
    esac
    shift
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
if (( $DISABLE_APACHE == 0 ))
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
	
	# Include SePHP mod_fastcgi configuration
	echo -e "\nAdding mod_fastcgi configuration for php-fpm ..."
	cp $cwd/configs/apache/conf.d/sephp.conf /etc/apache2/conf.d/ || exit 1

	# Disable default Apache virtualhost
	echo -e "\nDisabling default Apache virtualhost..."
	a2dissite default

	# Copy virtualhost into apache sites and enable it
	echo -e "\nEnabling SePHP Apache virtualhost ..."
	cat "$cwd/configs/apache/sites-available/sephp-vhost.conf" | 
		sed 's/\${vhost_root}/'"$ecwd\/webroot"'/g' >/etc/apache2/sites-available/sephp-vhost.conf
	a2ensite sephp-vhost.conf && /etc/init.d/apache2 restart || exit 1
fi

### Nginx configuration ###
if (( $DISABLE_NGINX == 0 ))
then
	echo "*** Nginx not yet implemented!"
fi

### php5-sephp module ###
echo -e "\nBuilding php5-sephp module ..."
cd php5-sephp
buildfail=0

phpize >/dev/null || buildfail=1
if (( buildfail != 0 )) ; then ./configure >/dev/null buildfail=1 ; fi
if (( buildfail != 0 )) ; then make >/dev/null || buildfail=1 ; fi

cd $cwd
if (( buildfail != 0 ))
then
	echo "*** Build of php5-sephp module failed." >&2 && exit 1
fi
