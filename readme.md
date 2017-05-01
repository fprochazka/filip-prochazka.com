# filip-prochazka.com

## Requirements

* [Docker Compose](https://github.com/docker/compose/releases) >= `1.12.0`
* [Docker Engine](https://www.docker.com/products/docker-engine) >= `1.13.0`

## Installation

Before you start anything, run the following command to setup the environment:

```bash
bash .docker/build-env.sh
docker-compose build node
```

## Before running

Install dependencies and compile javascript:

```bash
docker-compose run --rm composer install
docker-compose run --rm yarn-install
docker-compose run --rm yarn-build
```

This has to be done every time relevant files change.

## Running

```bash
docker-compose up app
```

and open http://localhost:8080
