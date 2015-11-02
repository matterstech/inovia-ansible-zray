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

# Check that web server is up and running
echo "it works" > /var/www/html/html_test.html
$PHP_CLI -r "echo file_get_contents('http://localhost/html_test.html');" | grep -q "^it" 
if [ $? -ne 0 ]; then
	echo "Cannot verify that your web server is up and running (checked http://localhost/html_test.html)."
	echo "Verify you meet the system requirements at http://files.zend.com/help/Z-Ray/content/system_requirements.htm"
	exit 2
fi

# Check that PHP works in web server
echo "<?php" > /var/www/html/php_test.php
echo "echo 'it works';" >> /var/www/html/php_test.php
$PHP_CLI -r "echo file_get_contents('http://localhost/php_test.php');" | grep -q "^it" 
if [ $? -ne 0 ]; then
	echo "Cannot verify that your web server handles PHP scirpts (is mod_php configured?)."
	echo "Verify you meet the system requirements at http://files.zend.com/help/Z-Ray/content/system_requirements.htm"
	exit 2
fi

# Check for mod_rewirte (needed for the UI)
if which apachectl > /dev/null 2> /dev/null; then
	apachectl -M | grep -q rewrite
	if [ $? -ne 0 ]; then
		echo "Cannot verify mod_rewrite is enabled."
		echo "Please make sure it appears in 'apachectl -M'"
		echo ""
		echo "Verify you meet the system requirements at http://files.zend.com/help/Z-Ray/content/system_requirements.htm"
		exit 2
	fi
else
	echo "Cannot find apachectl utility to verify mod_rewrite is enabled"
fi