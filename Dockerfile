FROM ruby:2.7-bullseye

WORKDIR /srv

COPY Gemfile /srv/
COPY Gemfile.lock /srv/

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
 && curl -sL https://dl.yarnpkg.com/debian/pubkey.gpg | gpg --dearmor | tee /usr/share/keyrings/yarnkey.gpg >/dev/null \
 && echo "deb [signed-by=/usr/share/keyrings/yarnkey.gpg] https://dl.yarnpkg.com/debian stable main" | tee /etc/apt/sources.list.d/yarn.list \
 && curl -fsSL https://deb.nodesource.com/setup_lts.x | bash - \
 && apt-get install -y --no-install-recommends \
    nodejs \
    yarn \
 && gem update --system \
 && bundle config set path "./vendor"
