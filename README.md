# Overview

There is a known issue when hosting in docker unfixed,
fix permission on storage/logs to 0777 if required.


# Docker
If run inside docker, can refer to below


Configurations
---
Nginx: .docker/etc/nginx
SSL: .docker/etc/ssl
FPM: .docker/etc/php



Launch with docker-compose 
---
MICROSERVICE_APIKEY is the key required to be posted as "key" in query for every request
HTTPS_PORT is the https port specified in docker-compose.yml
HTTP_PORT is the http port specified in docker-compose.yml

MICROSERVICE_APIKEY=somekey HTTPS_PORT=10444 HTTP_PORT=10088 docker-compose up -d


# Usage

ICMP
---
Method: POST
Path: /icmp/{host}
Curl: curl -k -X POST -d "key=abc" 'https://localhost:10443/icmp/google.com'

TCP
---
Method: POST
Path: /tcp/{host}:{port}
Curl: curl -k -X POST -d "key=abc" 'https://localhost:10443/tcp/google.com:443'