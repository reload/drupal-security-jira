FROM composer:2.8.6@sha256:d702aa6a31321b7c2f7e4258334ec965c2813859a2db3617b8a9f746b44e42c2 AS build-env

ENV PHP_EXTENSIONS="bcmath intl xsl"

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
# hadolint ignore=SC2086
RUN chmod +x /usr/local/bin/install-php-extensions && \
  install-php-extensions ${PHP_EXTENSIONS}

COPY . /opt/drupal-security-jira

WORKDIR /opt/drupal-security-jira

RUN composer install --prefer-dist --no-dev

FROM php:8.4.5-alpine3.20@sha256:48b414fa13e8019a8b34912ebd4762f05a216607f9e435ebf633d698354350b6

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
# hadolint ignore=SC2086
RUN chmod +x /usr/local/bin/install-php-extensions && \
 install-php-extensions ${PHP_EXTENSIONS}

COPY --from=build-env /opt/drupal-security-jira /opt/drupal-security-jira

ENTRYPOINT ["/opt/drupal-security-jira/bin/drupal-security-jira"]
CMD ["--verbose"]
