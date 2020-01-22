# Welcome to bookface, a social website for the 15th century

This site is meant as an educational tool for learning to set up and manage 'big web' applications. The code is not meant to run in production. It is meant to let aspiring sysadmins experience several fault-scenarios so they can improve their skills. 

# Installing dependencies (Assumes Ubuntu 14.04)

As a minimum requirement, bookface needs a MySQL database and a webserver with PHP installed as well as the proper libraries for PHP to communicate with the database. The database can be sepparated from the web server and you can have several webservers connected through a loadbalancer.

All the instructions assume you are root on the machines in question.

## Setting up the database

First, install the MySQL database (or MariaDB).

```
apt-get update
apt-get install mysql-server
```
Log into the database and set up the database with the correct permissions.

```
mysql -u root -p
```

Inside the database, create the databse and set the permissions:

```
create database bookface;
grant SELECT,UPDATE,INSERT,CREATE on bookface.* to bookfaceuser@127.0.0.1 identified by 'somepassword';
```

NOTICE: The mysql commads assume that the webserer resides on the same system as the databse. If you have multiple webservers you either have to use a wildcard for the location of the user or add one grant per IP address.

You should verify that the conenction works manually using the mysql client with the credentials you defined.

## Installing apache2

On the webserver hosts, install apache2 and the neccesary dependancies:

```
apt-get install apache2 libapache2-mod-php php-mysql
```

# Bookface installation instructions

Make sure you have git installed. This can be done with ```apt-get install git```

## Downloading the PHP files

```
git clone https://git.cs.hioa.no/kyrre.begnum/bookface.git
cd bookface
```

## Copy the files

Remove the default index.html file and move the main PHP files into apache's document root:

```
cp code/* /var/www/html/
rm /var/www/html/index.html
```

Next, make a copy of the example configuration file. You may want to keep this file sepparate from the git repository, since it can work with several versions of the code.

```
cp config_example.php config.php
```

Now, you can edit the config.php file and fill out the variables. If you are using a load balancer or otherwise external IP for your bookface, make sure to use that in your $webhost variable. When the config.php file is complete, copy it to the other PHP files:

```
cp config.php /var/www/html/
```

The last step is to initiate the tables. You can do so by calling the url:

```
curl http://localhost/createdb.php
```
