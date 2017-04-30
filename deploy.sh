#!/usr/bin/env bash

set -e

docker-compose run --rm composer install --no-interaction --prefer-dist
docker-compose run --rm yarn-install
docker-compose run --rm yarn-build
docker-compose build app
docker-compose down
rm -rf ./var/temp/cache/
rm -rf ./var/temp/content-cache/
docker-compose up -d app
