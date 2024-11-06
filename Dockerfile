FROM ruby:3.2.2-bookworm

WORKDIR /srv

COPY Gemfile /srv/
COPY Gemfile.lock /srv/
COPY package.json /srv/
COPY yarn.lock /srv/

RUN set -ex \
 && apt-get update \
 && apt-get upgrade -y \
 && apt-get install -y --no-install-recommends \
    zip \
    bash \
    curl \
    git \
    ca-certificates \
    python3-dev \
    build-essential \
 && curl -fsSL https://deb.nodesource.com/setup_22.x -o nodesource_setup.sh \
 && bash nodesource_setup.sh \
 && apt-get install -y --no-install-recommends \
    nodejs \
 && corepack enable \
 && corepack install \
 && gem update --system \
 && bundle config set path "./vendor" \
 && nodejs --version \
 && yarn --version \
 && ruby --version \
 && gem --version
