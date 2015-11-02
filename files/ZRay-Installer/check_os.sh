#!/bin/sh

SUPPORTED_OS='Mac OS X 10|CentOS Linux release 7|Red Hat Enterprise Linux Server release 7|Oracle Linux Server release 7|Debian GNU/Linux 8|Ubuntu 12|Ubuntu 13|Ubuntu 14|Ubuntu 15'


if `which lsb_release > /dev/null 2>&1`; then
	CURRENT_OS=`lsb_release -d -s`
elif [ -f /etc/system-release ]; then
	CURRENT_OS=`head -1 /etc/system-release`
elif [ -f /etc/issue ]; then
	CURRENT_OS=`head -2 /etc/issue`
else
  CURRENT_OS=$(echo $(sw_vers -productName 2>/dev/null) $(sw_vers -productVersion 2>/dev/null))
  if [ -z "${CURRENT_OS}" ]; then
    echo "Can't identify your system using lsb_release or /etc/issue"
    exit 1
  fi
fi
	
if ! echo $CURRENT_OS | egrep -q "$SUPPORTED_OS"; then
		echo "Z-Ray standalone does not support your Linux distribution."
		echo "Verify you meet the system requirements at http://files.zend.com/help/Z-Ray/content/system_requirements.htm"
		exit 1
fi