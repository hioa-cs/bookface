# Welcome to bookface, a fake social media site for learning about web architectures

This site is meant as an educational tool for learning to set up and manage 'big web' applications. The code is not meant to run in production. It is meant to let aspiring sysadmins experience several fault-scenarios so they can improve their skills. 

There are many different ways bookface can be deployed. This tutorial
goes through a simple setup using the two core components: a webserver
and a database. You can do everything on a single host or on sepparate
hosts, where one is a webserver and the other the database. The steps
for the webserver can be repeated on multiple webservers who are
behind a load-balancer, but we do not cover the steps of setting up
the load-balancer itself here.

**We assume that Ubuntu 22.04 is used as a base system.**

# Setting up the database (CockroachDB)

Bookface assumes a database within the Postgres family. In this case
we'll use CockroachDB, which is a modern database designed for large
deployments. One nice feature with this database, is that it comes
with a useful web-based status dashboard, which is quite useful when
you want to learn more about the life of a database.
CockroachDB actually assumes that you want to start with
a cluster of three nodes, but we'll settle for only one for now.

All the instructions assume you are root on the machines in question.

## Downloading and installing CockroachDB

You can find the official installation instructions here: 
https://www.cockroachlabs.com/docs/stable/install-cockroachdb-linux.html

Our instructions are adapted from theirs. At the time of writing, the latest version of the database is 19.2.2.
Be aware that the version number may have changed, in which case the
URL below needs to be changed as well.


```
wget https://binaries.cockroachdb.com/cockroach-v22.2.2.linux-amd64.tgz
tar xzf cockroach-v22.2.2.linux-amd64.tgz
cp cockroach-v22.2.2.linux-amd64/cockroach /usr/local/bin
```
The three above commands download the latest release as a compressed
archive. The tar command will extract the archive and finally we copy
the binary into the folder '/usr/local/bin', which enables us to
execute the binary as a normal command from the shell. 

You can verify these steps by executing the following command:
```
cockroach version
```
If you got a summary of the current version and so on, you are good to
go to the next step.

## Starting the database

Until now, we have only installed the datase, but we are not running
it. Before we start it, let's think about where all the data should be
stored. All persistent databases need somewhere to store all their
stuff. Let's create a folder for it, called `/bfdata` which will
reside at the root of the filesystem. You are free to use whatever
folder you want, just make sure that the root user (or whatever other
user you wish to use to run the database) has read/write access to it.

```
mkdir /bfdata
```

Next, let's start the database:
```
cockroach start --insecure --store=/bfdata --listen-addr=0.0.0.0:26257 --http-addr=0.0.0.0:8080 --background --join=localhost:26257
```
You can see that the `/bfdata` folder is present, which is good. In
addition, you see that we specify to addresses that the database will
listen to. The first one is `0.0.0.0:26257` and means that port 26257
 will be available on all network interfaces and that is the port
every database client will try to reach. The other one is for the
dasbboard, which is specified to be available on port 8080. 

There are some warnings which are displayd, but that is fine for us
right now. Please remember, that this tutorial assumes that you are in a safe lab
environment. We are obviously taking security-shortcuts here in order to get to
the core of the learning experience. 

The next step is to initialize the database:
```
cockroach init --insecure --host=localhost:26257
```

Once this command has finished, we should be able to see wether the
database is actually running in the background or not. The ps command
with list all running processes. Let's run it and filter the output to
only show lines that contain the text "cockroach":

```
ps aux | grep cockroach
```

You should be able to see a line corresponding to the running
database. This command can be used later as well, if you want to check
if the database is still running.


## Initializing the database

Now that the database is running, we need to log in and create the
user, tables and permissions. The easiest way to access the database,
is to use the cockroach command itself and open up a session: 

```
cockroach sql --insecure --host=localhost:26257
```

Notice that your command prompt has changed now that you have started
a command console inside the database. Next, you need to run the
following commands inside the console.


In these three commands, we create the database and define a user. There is no password here, so choose a different username if you want to avoid that others accidently log into your database.
```
CREATE DATABASE bf;
CREATE USER bfuser;
GRANT ALL ON DATABASE bf TO bfuser;
```
These commands will set up the tables (make sure you remember to
change the name of bfuser if you did so above):
```
USE bf;
CREATE table users ( userID INT PRIMARY KEY DEFAULT unique_rowid(), name STRING(50), picture STRING(300), status STRING(10), posts INT, comments INT, lastPostDate TIMESTAMP DEFAULT NOW(), createDate TIMESTAMP DEFAULT NOW());
CREATE table posts ( postID INT PRIMARY KEY DEFAULT unique_rowid(), userID INT, text STRING(300), name STRING(150), image STRING(32), postDate TIMESTAMP DEFAULT NOW());
CREATE table comments ( commentID INT PRIMARY KEY DEFAULT unique_rowid(), postID INT, userID INT, text STRING(300),  postDate TIMESTAMP DEFAULT NOW());
CREATE table pictures ( pictureID STRING(300), picture BYTES );
GRANT SELECT,UPDATE,INSERT to bfuser on TABLE bf.*;
```

You can end the session by typing `exit` and hitting enter.

## Viewing the CockroachDB dashboard

As mentioned earlier, an attractive feature of CockroachDB is its
web-based dashboard. This provides valuable insight into how the
database sees the world. We can see what queries are executed and how
long they take.

The dashboard is available on port 8080, since that was the port
chosen above. However, since the database does not (and should not)
have a floating IP, it is not available from the outside. One
therefore needs to use a tool such as FoxyProxy to browse the dashboard.


# Setting up a webserver

In this sections, we'll set up a webserver, which will hold the actual PHP code.

On the webserver host, install apache2 and the neccesary dependancies:

```
apt-get update
apt-get install apache2 libapache2-mod-php php-pgsql net-tools
```

You should be able to see that the apache2 webserver is running using
our trusty command `ps`. For example: 

```
ps aux | grep apache
```

Compared to cockroachdb, you will see more than one line. This is
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
git clone  https://git.cs.oslomet.no/kyrre.begnum/bookface.git
cd bookface
```

Now, remove the default index.html file and move the main PHP files into apache's document root:

```
rm /var/www/html/index.html
cp code/* /var/www/html/
```

One important part of how bookface works, is it's configuration file. This is where you will specify things like where the database is and the public URL will be. The configuration file is read every time a page is requested from a client and this means you can make dynamic changes to this file in order to change the behaviour of the site without restarting anything. However, it also means that an error in this file will cripple the site. It may be a good idea to come up with a scheme to make sure you have backup and some sort of oversight over this file, especially if you have many webservers.

Next, make a copy of the example configuration file. You may want to keep this file sepparate from the git repository, since it can work with several versions of the code.

```
cp config.php /var/www/html/config.php
```

Now, you can edit the config.php file and fill out the variables.

```
nano /var/www/html/config.php
```

Let's assume that the IP address of the database is 192.168.131.23 and we chose bfuser as a username. 
Let's also assume that the floating/public IP address (assuming you are in a cloud environment) is 10.20.0.43. 
Let's also assume that the port number of the database is 26257. The config.php file would then look like this: 
```
<?php
$dbhost = "192.168.131.23";
$dbport = "26257";
$db = "bf";
$dbuser = "bfuser";
$dbpassw = '';
$webhost = '10.20.0.43';
$weburl = 'http://' . $webhost ;
$frontpage_limit = 1000;
?>
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
<table>
<tr><td>Users: </td><td>0</td></tr>
<tr><td>Posts: </td><td>0</td></tr>
<tr><td>Comments: </td><td>0</td></tr>
</table>
```
If you can see the zeroes, it means we have a connection to the
database and the site is working as intended.


Likewise, in a web-browser you should se a relatively bare but
functioning website. Again, the number og users, posts and comments
should be zero.

If you got to this point, then you are done with your basic setup and
are ready for accepting users!

# How to remove users without images

There are several reasons why some unlucky users don't get any images.
Instead of deleting the entire database it may be better to simply
remove the particular users in question. However, this also means
removing there comments as well as their posts and the comments
on their posts from other users.

First, go to your database and start the console:
```
cockroach sql --insecure --host=localhost:26257
```

Inside the console run each of these commands (each of these is a
single long line):

```
use bf;

delete from comments where exists (select postid from posts where postid = comments.postid and exists ( select * from users where posts.userid = users.userid and not exists ( select * from pictures where pictureid = users.picture)));

delete from comments where exists ( select * from users where comments.userid = users.userid and not exists ( select * from pictures where pictureid = users.picture));

delete from posts where exists ( select * from users where posts.userid = users.userid and not exists ( select * from pictures where pictureid = users.picture));

delete from users where not exists ( select * from pictures where pictureid = users.picture);
```
