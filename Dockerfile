FROM composer:2.7.1@sha256:da5213f1c0c4db435ad335be4e782ebf8677b45d37677a6db1e73e474c7ad947 AS build-env

ENV PHP_EXTENSIONS="bcmath intl xsl"

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
# hadolint ignore=SC2086
RUN chmod +x /usr/local/bin/install-php-extensions && \
  install-php-extensions ${PHP_EXTENSIONS}

COPY . /opt/drupal-security-jira

WORKDIR /opt/drupal-security-jira

RUN composer install --prefer-dist --no-dev

FROM php:8.3.3-alpine3.18@sha256:923f550b2f366561f6f2a5a08e8dd9d6d8be2bfe7abc2598549a6b91f95d6abd

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
# hadolint ignore=SC2086
RUN chmod +x /usr/local/bin/install-php-extensions && \
 install-php-extensions ${PHP_EXTENSIONS}

COPY --from=build-env /opt/drupal-security-jira /opt/drupal-security-jira

ENTRYPOINT ["/opt/drupal-security-jira/bin/drupal-security-jira"]
CMD ["--verbose"]
