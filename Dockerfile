FROM composer:2.7.6@sha256:23beecbb2cf15f2bddeb8115f877896ade09e47cf44ccf341204ed1d947a0f38 AS build-env

ENV PHP_EXTENSIONS="bcmath intl xsl"

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
# hadolint ignore=SC2086
RUN chmod +x /usr/local/bin/install-php-extensions && \
  install-php-extensions ${PHP_EXTENSIONS}

COPY . /opt/drupal-security-jira

WORKDIR /opt/drupal-security-jira

RUN composer install --prefer-dist --no-dev

FROM php:8.3.7-alpine3.18@sha256:3da837b84db645187ae2f24ca664da3faee7c546f0e8d930950b12d24f0d8fa0

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
# hadolint ignore=SC2086
RUN chmod +x /usr/local/bin/install-php-extensions && \
 install-php-extensions ${PHP_EXTENSIONS}

COPY --from=build-env /opt/drupal-security-jira /opt/drupal-security-jira

ENTRYPOINT ["/opt/drupal-security-jira/bin/drupal-security-jira"]
CMD ["--verbose"]
