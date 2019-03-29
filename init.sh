#!/bin/bash -x 

# env | grep _ >> /etc/apache2/envvars
# echo "export BF_DB=dookfacedb" >> /etc/apache2/envvars

# if [ -n "$GLUSTER_IMAGE_VOL" ]; then

#  echo "GlusterFS enabled for images. Source: $GLUSTER_IMAGE_VOL";
#  if mount -t glusterfs $GLUSTER_IMAGE_VOL /var/www/html/images; then
#   echo "Volume mounted successfully!";
#  else 
#   tail /var/log/syslog
#   echo "failed to mount volume!"
#   exit 1;
#  fi
# fi
chmod 777 /var/www/html/images
/usr/sbin/apache2ctl -D FOREGROUND -k start
