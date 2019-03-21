# Setting up bookface to work with a gluster backend for images

This guide is in order to set up bookface as a dockerized service,
including memcache.

## Mise en place


This guide assumes the following: 


* You have docker swarm ( a single node will work )
* you are using the latest version of bookface
* You also have a cockroach database which is initialized and has a database with tables ready, as described in this guide:
* You have a GlusterFS cluster with a volume, called "images"

## Setting up the GlusterFS volume driver on the swarm

You have to repeat these steps ON ALL nodes in your swarm!

1. Set hostnames with IP's in /etc/hosts. For example:

```
192.168.128.108 gluster-1
192.168.128.241 gluster-2
192.168.128.98 gluster-3
```

2. Install the volume driver plugin:

```
docker plugin install --alias gluster trajano/glusterfs-volume-plugin --grant-all-permissions --disable
docker plugin set gluster SERVERS=gluster-1,gluster-2,gluster-3
docker plugin enable gluster
```

3. Start the bf stack


```
docker stack deploy -c docker-compose-gluster.yml --with-registry-auth bf
```


You should keep track if the services start as desired:


```
docker service ls | grep bf
```


The service is set up to only run one replica of the webserver. This is wise when you want to make sure everything works as intended. If you want more information, try:


```
docker service ps --no-trunc bf_web
```


## Management operations

### Adjusting the number of replicas
Once the service is working properly, you can adjust the number of replicas that are running:


```
docker service update --replicas=3 bf_web
```


### Adjusting the frontpage limit

The default image used by the docker compose file, can adjust the frontpage_limit variable based on an environment variable. There is no need to change the container image and the adjustment can be done dynamically.

In this example, we adjust the frontend_limit to be 100:


```
docker service update --env-add BF_FRONTPAGE_LIMIT=100 --with-registry-auth bf_web
```



