# Welcome to bookface, a fake social media site for learning about web architectures

This site is meant as an educational tool for learning to set up and manage 'big web' applications. The code is not meant to run in production. It is meant to let aspiring sysadmins experience several fault-scenarios so they can improve their skills. 

There are many different ways bookface can be deployed. This tutorial
goes through a simple setup using the two core components: a webserver
and a database. You can do everything on a single host or on sepparate
hosts, where one is a webserver and the other the database. The steps
for the webserver can be repeated on multiple webservers who are
behind a load-balancer, but we do not cover the steps of setting up
the load-balancer itself here.

**We assume that Ubuntu 24.04 or later is used as a base system.**

# Installing the database (Yugabyte)

Bookface assumes a database within the Postgres family. In this case
we'll use Yugabyte, which is a modern database designed for large
deployments. One nice feature with this database, is that it comes
with a useful web-based status dashboard, which is quite useful when
you want to learn more about the life of a database.
Yugabyte actually assumes that you want to start with
a cluster of three nodes, but we'll settle for only one for now.

*All the instructions assume you are root on the machines in question.*

## Installing chrony

Databases are dependent on having an accurate clock, so let's install chrony in order to keep the clock synchronized.

```
apt-get install -f chrony
```

## Downloading and installing Yugabyte

Next, we'll download and unpack the Yugabyte package: 

```
wget https://software.yugabyte.com/releases/2025.2.0.0/yugabyte-2025.2.0.0-b131-linux-x86_64.tar.gz
tar xvfz yugabyte-2025.2.0.0-b131-linux-x86_64.tar.gz && cd yugabyte-2025.2.0.0/
./bin/post_install.sh
```


## Create data directory

Until now, we have only installed the database, but we are not running
it. Before we start it, let's think about where all the data should be
stored. All persistent databases need somewhere to store all their
stuff. Let's create a folder for it, called `/bfdata` which will
reside at the root of the filesystem. You are free to use whatever
folder you want, just make sure that the root user (or whatever other
user you wish to use to run the database) has read/write access to it.

```
mkdir /bfdata
```

## Start database

Before starting the dataabse, you need to get the IP address of your server. This address will used instead of "SERVER_IP" in the below commands. In order to get the IP address, you can run the following command: 

```
ip -c a show ens3
```

Next, we are ready to start the database:
```
./bin/yugabyted start --advertise_address=SERVER_IP --base_dir=/bfdata
```


## Install the Yugabyte shell

For the most part, one has to be in the Yugabyte directory to run several of the commands, but the Yugabyte shell can be linked to your path, so you can run it from any folder:

```
ln -s $PWD/bin/ysqlsh /usr/bin/ysqlsh
```

You can test that it works with this command (it will only output the version number, but thats all we want)

```
ysqlsh --version
```

## Viewing the Yugabyte dashboard

As mentioned earlier, an attractive feature of Yugabyte is its
web-based dashboard. This provides valuable insight into how the
database sees the world. We can see what queries are executed and how
long they take.

The dashboard is available on port 15433. However, since the database does not (and should not)
have a floating IP, it is not available from the outside. One
therefore needs to use a tool such as FoxyProxy to browse the dashboard.

## Bootstrap the database

The first thing we should do, is intitialize the database. We do that by downloading a file containing all the needed SQL commands and run it on the database.

Let's first clone the githup repository:

```
git clone https://github.com/hioa-cs/bookface.git
cd bookface
```

Open the file bootstrap_db.sql with an editor, such as nano:

```
nano bootstrap_db.sql
```

For a bit of extra security, it is advisable to use a different username than bfuser, but be aware that you have to remember to use your chosen alternative username several places during this setup. regardless of the extra hassle, it is the better choice. 

if you chose to change the username, then the bootstrap file has to be updated four places: two lines near the top and two lines near the bottom. Once the update is done, save the file and exit nano.

You are now ready to feed the sql code to the database, which will create the bf database with all its tables:

```
ysqlsh -h SERVER-IP -f bootstrap_db.sql
```


# Setting up a webserver

In this sections, we'll set up a webserver, which will hold the actual PHP code.

On the webserver host, install apache2 and the neccesary dependancies:

```
apt-get update
apt-get install apache2 libapache2-mod-php php-pgsql net-tools php-memcache
```

You should be able to see that the apache2 webserver is running using
our trusty command `ps`. For example: 

```
ps aux | grep apache
```

You will see more than one line. This is
because Apache forks itself into several threads in order to be able
to handle several web-connections at the same time. If this was a busy
webserver, you might get even more lines.

Apache listens on port 80. We installed the package "net-tools" which
gives us a handy way to check who is listening on what port. Try the
following: 

```
netstat -anltp
```

There should be one line here, where we listen on port 80, owned by
the process apache2. 


The next step is to download the bookface code from the git repository.
```
git clone https://github.com/hioa-cs/bookface.git
cd bookface
```


## Copy the code files

Now, remove the existing index.html file and move the bookface code into apache's document root:

```
rm /var/www/html/index.html
cp code/* /var/www/html/
```

One important part of how bookface works, is it's configuration file. This is where you will specify things like where the database is and the public URL will be. The configuration file is read every time a page is requested from a client and this means you can make dynamic changes to this file in order to change the behaviour of the site without restarting anything. However, it also means that an error in this file will cripple the site. It may be a good idea to come up with a scheme to make sure you have backup and some sort of oversight over this file, especially if you have many webservers.

Next, make a copy of the example configuration file. You may want to keep this file sepparate from the git repository, since it can work with several versions of the code.

```
cp config.json /var/www/html/config.json
```

Now, you can edit the config.json file and fill out the variables.

```
nano /var/www/html/config.json
```

The file is empty, which means that bookface will not be able to work very well. Let's assume that the IP address of the database is 192.168.131.23 and we chose bfuser as a username. 
Let's also assume that the floating/public IP address (assuming you are in a cloud environment) is 10.20.0.43. 
Let's also assume that the port number of the database is the default 5433. The config.json file would then look like this: 
```
{
  "dbhost" : "192.168.131.23",
  "dbport" : "5433",
  "db" : "bf",
  "dbuser" : "bfuser",
  "dbpassw" : "",
  "webhost" : "10.20.0.43",
  "frontpage_limit" : "1000"
}
```

## Test the setup

At this point, you are finally ready to test the entire setup. If this works, the next step will be to populate it with a few users.

There are two ways to test that everything works, on the command-line
and in a web-browser. On the command-line, we would use something like
curl or wget to see if we get a HTML output which makes sense. 

```
curl http://floating-ip
```

The information to look for, is this part:
```
<table class=info-table >
<tr><td>Users: </td><td>0</td></tr>
<tr><td>Posts: </td><td>0</td></tr>
<tr><td>Comments: </td><td>0</td></tr>
</table>
```
If you can see the zeroes, it means we have a connection to the
database and the site is working as intended!


Likewise, in a web-browser you should se a relatively bare but
functioning website. Again, the number og users, posts and comments
should be zero.

If you got to this point, then you are done with your basic setup and
are ready for accepting users!


