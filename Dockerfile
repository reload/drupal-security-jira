FROM composer:2.8.4@sha256:e4d7757e0c7ae48d9ddc65c07215ce602cbdcc909dc0dd8e30626ffd865fc968 AS build-env

ENV PHP_EXTENSIONS="bcmath intl xsl"

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
# hadolint ignore=SC2086
RUN chmod +x /usr/local/bin/install-php-extensions && \
  install-php-extensions ${PHP_EXTENSIONS}

COPY . /opt/drupal-security-jira

WORKDIR /opt/drupal-security-jira

RUN composer install --prefer-dist --no-dev

FROM php:8.4.2-alpine3.20@sha256:5fd67fe49d930edbd2cf62d5dfc076a7a666f10e184f6799404ff88690500bf0

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
# hadolint ignore=SC2086
RUN chmod +x /usr/local/bin/install-php-extensions && \
 install-php-extensions ${PHP_EXTENSIONS}

COPY --from=build-env /opt/drupal-security-jira /opt/drupal-security-jira

ENTRYPOINT ["/opt/drupal-security-jira/bin/drupal-security-jira"]
CMD ["--verbose"]
