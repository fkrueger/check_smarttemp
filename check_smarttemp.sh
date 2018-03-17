#!/bin/bash

# Nagios plugin to check S.M.A.R.T. temperatures from your hdds
#
# * Requires hddtemp
#
# (c) 2013,2017 by Frederic Krueger / fkrueger-dev-checksmarttemp@holics.at
#
# Licensed under the Apache License, Version 2.0
# There is no warranty of any kind, explicit or implied, for anything this software does or does not do.
# 
# Updates for this piece of software could be available under the following URL:
#   GIT: https://github.com/fkrueger-2/check_smarttemp
#   Home: http://dev.techno.holics.at/check_smarttemp/

# This should work without root permissions, but just in case it doesn't, use this in your sudoers file as well as
# the nagios servicecheck definition below:
#
# nagios  ALL=(root) NOPASSWD: /usr/lib/nagios/plugins/check_smarttemp.sh *



### globals
HDDTEMP="/usr/sbin/hddtemp"
[ ! -e "$HDDTEMP" ] && HDDTEMP=`which hddtemp`

######## don't change anything below here!! ########






PROG_NAME="check_smarttemp"
PROG_VERSION="0.0.4"


## nagios exit codes
OK=0
WARNING=1
CRITICAL=2
UNKNOWN=3



### the func starts here:
function usage()
{
  echo ""
	echo "Usage: ./check_smarttemp.sh <device> <warn> <crit>"
  echo ""
  echo "   This plugin allows you to check your hdds' S.M.A.R.T. temperatures."
  echo "   For that it requires hddtemp."
  echo ""
  echo "   <device> can be things like sda (for /dev/sda) or '-a' for all physical devices in /proc/partitions"
  echo ""
  echo ""
  echo '### NAGIOS COMMANDS DEFINITION
define command{
  command_name  check_smarttemp
  command_line  $USER1$/contrib/check_smarttemp.sh $ARG1$ $ARG2$ $ARG3$ $ARG4$ $ARG5$ $ARG6$
}

### NAGIOS SERVICECHECK DEFINITION
define service{
  use                      local-service         ; Name of service template to use
  host_name                yourhost
  service_description      Sensor SMART
  check_command            check_smarttemp!all!40!50
}

### PNP4NAGIOS check_commands configuration check_smarttemp.cfg
CUSTOM_TEMPLATE = 0, 1

### PNP4NAGIOS template definition check_smarttemp.php (better get this file from the package)

'

# licensing info
echo "$PROG_NAME v$PROG_VERSION is licensed under the Apache License, Version 2.0 .
There is no warranty of any kind, explicit or implied, for anything this software does or does not do.

(c) 2013,2017 by Frederic Krueger / fkrueger-dev-checksmarttemp@holics.at

"
}


function checkdetect_device()
{
  if [ "x$DEVICELIST" == "x-a" ] || [ "x$DEVICELIST" == "xall" ]; then
    DEVICELIST=`cat /proc/partitions | awk '{print $4}' | grep -vP '^(dm|md|name|.*[0-9]+$|$)' | xargs`
  fi

  # make sure devices are indeed block devices
  for dev in $DEVICELIST; do
  	if [ ! -b "/dev/$dev" ];then
	  	echo "UNKNOWN: /dev/$dev needs to exist / be a block device."
  		exit $UNKNOWN
  	fi
  done
}


## get hdd temperatures
function get_hddtemps()
{
	# gets temperature and stores it in $HEAT
	# and make sure we get a numeric output
  DEVSTR=""
  DEVICESARR=( $DEVICELIST )
  for i in {0..63}; do
    if [ "x${DEVICESARR[$i]}" != "x" ]; then
      DEVSTR="${DEVSTR} /dev/${DEVICESARR[$i]}"
    fi
  done

  CMD="$HDDTEMP $DEVSTR -n"

  HEATS=`$CMD 2>/dev/null | xargs`
  if [ "x$HEATS" == "x" ]; then
    echo "UNKNOWN: Problem getting temperature(s) for the device(s): $DEVSTR"
    echo "Command used: '$CMD'"
    exit $UNKNOWN
  else
    HEATSARR=($HEATS)
  fi
}

# do the actual check (includes creating perfdata and more or less useful output)
function check_heats()
{
  PERFDATA=""
  INFOOUT=""
  RC=$OK
  for i in {0..63}; do
    if [ "x${DEVICESARR[$i]}" != "x" ] && [ "x${HEATSARR[$i]}" != "x" ]; then
      INFOOUT="${INFOOUT} ${DEVICESARR[$i]}=${HEATSARR[$i]}"
      PERFDATA="${PERFDATA} temp_${DEVICESARR[$i]}=${HEATSARR[$i]};$WARN;$CRIT;;"
      [ "${HEATSARR[$i]}" -gt $WARN ] && [ "$RC" -lt $WARN ] && RC="$WARN"
      [ "${HEATSARR[$i]}" -gt $CRIT ] && [ "$RC" -lt $CRIT ] && RC="$CRIT"
    fi
  done

  # trim leading space
  INFOOUT="${INFOOUT:1}"
  PERFDATA="${PERFDATA:1}"

	if [ $RC == $OK ];then
		echo "OK - Temperatures are $INFOOUT | $PERFDATA"
		exit $OK
	elif [ $RC == $WARN ];then
		echo "WARNING - Temperatures are $INFOOUT | $PERFDATA"
		exit $WARNING
	elif [ $RC == $CRIT ];then
		echo "CRITICAL - Temperatures are $INFOOUT | $PERFDATA"
		exit $CRITICAL
  # UNKNOWN doesn't happen at this point, unless someone decides to tamper with the script.
	fi
}





### main

## arguments:
if [ "x$3" == "x" ]; then
  usage
	exit $UNKNOWN
fi

## .. parsing
DEVICELIST=$1
WARN=$2
CRIT=$3

## .. checking
if [ $WARN -ge $CRIT ];then
  echo "UNKNOWN: WARN must be lower than CRIT"
  exit $UNKNOWN
fi

## start the show.
checkdetect_device "$DEVICELIST"
get_hddtemps "$DEVICELIST"
check_heats "$DEVICELIST"

#fin.
