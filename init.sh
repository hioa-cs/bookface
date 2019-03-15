#!/bin/bash

# env | grep _ >> /etc/apache2/envvars
# echo "export BF_DB=dookfacedb" >> /etc/apache2/envvars

/usr/sbin/apache2ctl -D FOREGROUND -k start
