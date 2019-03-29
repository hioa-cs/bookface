
# Importing data from existing bookfaces

With the latest tag "v7", one can now import data from an existing bookface service. The purpose of this tool is to allow you to migrate to new installations of the service, even if the database has changed.

The current solution is written in PHP and is a part of the new codebase. This means that if you are running a recent container version, then this feature will be available.

When you have done the preparations, you can import the data by calling a special url on the new system.

## Preparation:

The following assumptions are being made:

* You have two bookface systems running. One old and one new.

* The old system is operational and can be accessed via an URL ( does not have to be a floating IP )

* The new system has the database set up with appropriate tables, but contains no data.

In order to protect your new system from others pushing data into yours, you first have to set up a secret key in the new database. This way, only someone knowing the key will be able to initiate migration.

I'm assuming you are running cockroachdb on the new system. Start by opening a console to it. Do this from the docker master:

```
docker run -it --rm --network=roach_cockroachdb cockroachdb/cockroach:v2.1.6 sql --host=cockroachdb-1 --insecure
```
Inside the console, execute the following ( change 'yoursecretkey' with something else ):

```
use bf;
CREATE table config ( key STRING(100), value STRING(500) );
insert into config ( key, value ) values ( 'migration_key', 'yoursecretkey' );
```
You can now disconnect from the console. 

## Start the migration

The migration can be initiated from anywhere where you can reach the new system, so it does not have to be publicly available while you move the data over. Start the migration by calling a particular URL, preferrably from a commamnd-line and not in a browser.

Also, this would be a good time to start a screen session and continue from inside it. You do not want to loose connectivity while this is ongoing.

Assuming your new systems IP is 192.168.128.223 with a port number 30002, and your old systems IP is 192.168.128.33 and the key is as described above, the resulting command would be (we'll also add the time command to track how long it takes):

```
time curl -v 'http://192.168.128.223:30002/import.php?entrypoint=192.168.128.33&key=yoursecretkey'
```

Notice the use of single quotes around the URL in order to avoid confusion due to the "&" symbol used to sepparate variables.

This process will take some time, and you can keep track as you watch the output from the curl command.

At the end of the execution, you would see a text like this:

```
<h1>Added 112 users</h1>

</HTML>
```

## What if the call quits with an error before it has finished?

This happens. The import script is designed to continue where it left of last time, so you should be fine running it again and again until all the data is moved. It would be worse if you execute it several times at the same time. So make sure the process has really finished before you start over. For instance, if you lost connectivity, be sure to re-connect to the screen session first.

## Something went terribly wrong and I need to start over

Sure. The original data is still there, so just import it again. But first:

* Clear the data from the databse, by going back to the cockroachdb console and running:
  ```
  delete from users where userid >= 1; delete from posts where postid >= 1; delete from comments where commentid >= 1;
  ```

* Remove the images in the "images" glusterFS volume ( not strictly needed ).

