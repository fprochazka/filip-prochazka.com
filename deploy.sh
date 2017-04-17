#!/usr/bin/env bash

set -e

docker-compose run --rm composer install --no-interaction --prefer-dist
docker-compose run --rm node yarn install
docker-compose run --rm node yarn build
docker-compose build app
docker-compose down
docker-compose up -d app
