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

# Check PHP extensions
REQ_EXT="ctype curl json pdo_sqlite SimpleXML zip";
for ext in $REQ_EXT; do
	$PHP_CLI -m | grep -q ^$ext || MISSING_EXT="$ext $MISSING_EXT";
done
if [ -n "$MISSING_EXT" ]; then
	echo "Verify you meet the system requirements at http://files.zend.com/help/Z-Ray/content/system_requirements.htm"
	echo "The following PHP extensions are required by Z-Ray: $REQ_EXT"
	echo "The following PHP extensions are missing: $MISSING_EXT"
	exit 2
else
	echo "Found all required PHP extensions: $REQ_EXT"
fi