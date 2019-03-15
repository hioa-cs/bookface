FROM ubuntu:18.04
MAINTAINER kyrre@begnum.no
ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get update
RUN apt-get install -y apache2 libapache2-mod-php php-mysql git php-memcache curl php-cli php-mbstring unzip php-pgsql

RUN rm /var/www/html/index.html

ADD code/* /var/www/html/
ADD config.php /var/www/html

EXPOSE 80

ENTRYPOINT ["/init.sh"]

