#!/bin/sh

# No parameters
if [ $# -lt 1 ]; then
	PHP_CLI=""
	if which php > /dev/null 2> /dev/null; then
		PHP_CLI=`which php`
	else
		echo "Cannot find PHP location in PATH."
		echo ""
		echo "Verify you meet the system requirements at http://files.zend.com/help/Z-Ray/content/system_requirements.htm"
		echo "Please make sure PHP is installed or supply a path to PHP CLI as an argument for this script."
		exit 2
	fi
else
	if [ -x $1 ]; then
		PHP_CLI=$1
	else
		echo "Cannot execute $1, please try again with a correct path."
		exit 2
	fi
fi

# Verify parameter
if [ -x $PHP_CLI ]; then
	$PHP_CLI -v > /dev/null
	if [ $? -eq 0 ]; then
		PHP_VERSION=`$PHP_CLI -v | head -1 | cut -f2 -d" "`
	else
		echo "$PHP_CLI has errors, please fix them and try running this script again."
		exit 2
	fi
elif [ -f $PHP_CLI ]; then
	echo "Found $PHP_CLI but cannot execute it (probably due to wrong permissions)."
	exit 2
fi

# Check PHP major
INSTALLED_PHP_MAJOR=`echo $PHP_VERSION | cut -f1,2 -d"."`
if [ "$INSTALLED_PHP_MAJOR" != "5.5" -a "$INSTALLED_PHP_MAJOR" != "5.6" ]; then
	echo "Verify you meet the system requirements at http://files.zend.com/help/Z-Ray/content/system_requirements.htm"
	echo "Z-Ray standalone only supports PHP versions 5.5 and 5.6."
	exit 2
fi

echo $INSTALLED_PHP_MAJOR