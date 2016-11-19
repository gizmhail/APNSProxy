#!/bin/bash
if [ "$1" != "" ] 
then
	appName=$1
	if [ ! ${appName}.p12 ]; then
		echo "${appName}.p12 does not exist"
	else
		echo "Generating pem file from ${appName}.p12"
		openssl pkcs12 -in ${appName}.p12 -out ${appName}.pem -nodes -clcerts
	fi
else 
	echo "Convert appname.p12 to appname.pem (your passphrase will be requested)"
	echo "Usage: $0 appname"
fi

