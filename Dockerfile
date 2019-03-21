FROM ubuntu:18.04
MAINTAINER kyrre@begnum.no
ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get update &&  apt-get install -y apache2 libapache2-mod-php php-mysql git php-memcache curl php-cli php-mbstring unzip php-pgsql glusterfs-client

RUN rm /var/www/html/index.html
RUN mkdir /var/www/html/images
ADD code/* /var/www/html/
ADD config.php /var/www/html
ADD init.sh /
EXPOSE 80

ENTRYPOINT ["/init.sh"]

