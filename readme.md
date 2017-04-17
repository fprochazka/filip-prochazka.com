# filip-prochazka.com

## Requirements

* [Docker Compose](https://github.com/docker/compose/releases) >= `1.12.0`
* [Docker Engine](https://www.docker.com/products/docker-engine) >= 1.13.0

## Setup

Before you start anything, run the following command to setup environment

```bash
bash .docker/build-env.sh
docker-compose build node
```

And also compile javascript

```bash
docker-compose run --rm node yarn install
docker-compose run --rm node yarn build
```

## Running

```bash
docker-compose up app
```

and open http://localhost:8080
