#!/usr/bin/env bash

echo "PRODUCTION=false" > ./.env
echo "USER_UID=$(id -u)" >> ./.env
echo "USER_GID=$(id -g)" >> ./.env
echo "USER_NAME=$(id -un)" >> ./.env
echo "HTTP_PORT=8080" >> ./.env
