# Simple Docker example

This example provides a simple way to set up bookface on Docker with one database instance and one webserver instance

## Preparations

1. Make sure you have Docker installed on your host. This example assumes Docker on Linux.
2. Note down the IP address of the local host you are using. We'll call it "HOST_IP" here. 
3. If you run this on a virtual machine inside a cloud and use a "Floating IP" to access your system, then also note down that IP. We'll call it "FLOATING_IP" in this example. 
4. Make sure port 80 and port 9080 are accessible in the security groups or firewall.

## 1. Create the folders for data and images

The folder `bf_data` will be used by the database and `bf_images` will be where the webserver stores and fetches images.

```
mkdir bf_data
mkdir bf_images
```

## 2. Start the database

We'll use CockroachDB in this example. It is mainly a Postgres-like database, but designed to run as a cluster. It also offers a nice web-dashboard.

```
docker run -d --name=cockroachdb -p 26257:26257 -p 9080:8080  -v "${PWD}/bf_data:/cockroach/cockroach-data"  cockroachdb/cockroach:v20.1.4 start-single-node --insecure
```

Notice, that the dashboard port will be available from the outside at 9080 and not the default 8080. You can now check whether the dashboard is available using 

http://FLOATING_IP:9080

## 3. Initialize the database 

Now that the database is running, we need to initialize it with the right tables etc. First, let's establish a console into the database itself:
```
docker exec -it cockroachdb ./cockroach sql --insecure
```
Inside the shell, copy and paste the following.

In these three commands, we create the database and define a user. There is no password here, so choose a different username if you want to avoid that others accidently log into your database.
```
CREATE DATABASE bf;
CREATE USER bfuser;
GRANT ALL ON DATABASE bf TO bfuser;
```
These commands will set up the tables:
```
USE bf;
CREATE table users ( userID INT PRIMARY KEY DEFAULT unique_rowid(), name STRING(50), picture STRING(300), status STRING(10), posts INT, comments INT, lastPostDate TIMESTAMP DEFAULT NOW(), createDate TIMESTAMP DEFAULT NOW());
CREATE table posts ( postID INT PRIMARY KEY DEFAULT unique_rowid(), userID INT, text STRING(300), name STRING(150), postDate TIMESTAMP DEFAULT NOW());
CREATE table comments ( commentID INT PRIMARY KEY DEFAULT unique_rowid(), postID INT, userID INT, text STRING(300),  postDate TIMESTAMP DEFAULT NOW());
CREATE table pictures ( pictureID STRING(300), picture BYTES );
```

You can end the session by typing `exit` and hitting enter.

You should be able to see the respective tables in the "Databases" category in the CockroachDB dashboard.

## 4. Start the webserver

Next we start the webserver using the bookface Docker image. Note the use of HOST_IP in the below command. Also, if you chose a different username in step 3, then change that as well.

```
docker run --name=bfweb -d -p 80:80 -v ${PWD}/bf_images:/var/www/html/images -e BF_DB_HOST=HOST_IP -e BF_DB_PORT=26257 -e BF_DB_USER=bfuser -e BF_DB_NAME=bf docker.cs.hioa.no/kyrrepublic/bf:latest
```

When this is done, you should be able to see a simple bookface webpage at `http://FLOATING_IP`. It should show the number "0" on users, posts and comments. That is a sign the webserver successfully conencted to the database.

## 5. Adding some content

This command will launch a docker instance which will start using the bookface page. It starts by waiting 40 seconds before it creates a few users and then starts to alternate between random posts, comments or new uesrs.

It may be wise to run the command in the foreground, meaning that bookface will only see activity while this instance is active. If you run it in the background, you may as well fill up your disk and experience added background activity on your system.

```
docker run -ti -e INTERVAL=10 -e ENTRYPOINT=HOST_IP -e NOWAIT=True docker.cs.hioa.no/kyrrepublic/simplewebuser:latest
```
While it is running, you can refresh your bookface page to see the activity. You can also monitor the CockroachDB dashboard to observe the database at work.

