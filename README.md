# Overview

There is a known issue when hosting in docker unfixed,
fix permission on storage/logs to 0777 if required.


# Docker
If run inside docker, can refer to below


Configurations
---
Nginx: `.docker/etc/nginx` 

SSL: `.docker/etc/ssl` 

To use other certicates, please visit this folder and change certs, chained certs or other advanced configuration please refer to nginx folder.

FPM: `.docker/etc/php` 



Launch with docker-compose 
---
MICROSERVICE_APIKEY is the key required to be posted as "key" in query for every request

HTTPS_PORT is the https port specified in docker-compose.yml  

HTTP_PORT is the http port specified in docker-compose.yml 


`DESCRIPTOR=HONGKONG MICROSERVICE_APIKEY=somekey HTTPS_PORT=10443 HTTP_PORT=10080 docker-compose up -d`

After initial launch, there will be downtime due to composer fetching new libraries, to check progress, please use
`docker-compose logs --tail 50 -f composer`


# Usage

Basic info (i.e. fetching descriptor set above)
---
Method: POST

Path: /info

Curl: `curl -k -X POST -d "key=somekey" 'https://localhost:10443/info'`

ICMP
---
Method: POST

Path: /icmp/{host}

Optional query: packets (default: 3), when specified 1 ~ 5, it will emit a request per second

Optional query: complex (default: no), when specified yes, the result will be verbose

Curl: `curl -k -X POST -d "key=somekey" 'https://localhost:10443/icmp/google.com'`

TCP
---
Method: POST

Path: /tcp/{host}:{port}

Curl: `curl -k -X POST -d "key=somekey" 'https://localhost:10443/tcp/google.com:443'`
