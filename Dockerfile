FROM composer:2.10.0@sha256:1b73755de4f19775ba6087fd5313664493e06fab72b6fc27dc2044e87bb7c4c3 AS build-env

ENV PHP_EXTENSIONS="bcmath intl xsl"

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
# hadolint ignore=SC2086
RUN chmod +x /usr/local/bin/install-php-extensions && \
  install-php-extensions ${PHP_EXTENSIONS}

COPY . /opt/drupal-security-jira

WORKDIR /opt/drupal-security-jira

RUN composer install --prefer-dist --no-dev

FROM php:8.4.8-alpine3.20@sha256:8d60c4303cfc7aad89ab800e9603096c8f630bcb552697401f4761caaec03cfb

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
# hadolint ignore=SC2086
RUN chmod +x /usr/local/bin/install-php-extensions && \
 install-php-extensions ${PHP_EXTENSIONS}

COPY --from=build-env /opt/drupal-security-jira /opt/drupal-security-jira

ENTRYPOINT ["/opt/drupal-security-jira/bin/drupal-security-jira"]
CMD ["--verbose"]
