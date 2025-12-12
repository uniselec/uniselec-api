FROM php:8.2-apache as dev

ENV APP_ENV=dev
ENV APP_DEBUG=true
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV CACHE_DRIVER=file
ENV SESSION_DRIVER=file
ENV QUEUE_CONNECTION=sync
ENV APP_TIMEZONE=America/Fortaleza
ENV DEBIAN_FRONTEND noninteractive

SHELL ["/bin/bash", "-o", "pipefail", "-c"]

RUN { \
  echo "upload_max_filesize=5M"; \
  echo "post_max_size=6M"; \
  } > /usr/local/etc/php/conf.d/uploads.ini


RUN apt-get update \
  && apt-get install -y --no-install-recommends \
  locales \
  curl \
  nano \
  unzip \
  gnupg \
  ca-certificates \
  libfreetype6-dev \
  libjpeg62-turbo-dev \
  libpng-dev \
  libzip-dev \
  default-mysql-client \
  iputils-ping \
  netcat-openbsd \
  && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*


COPY . /var/www/html

WORKDIR /var/www/html


RUN docker-php-ext-install pdo_mysql mysqli \
  && docker-php-ext-configure opcache --enable-opcache \
  \
  && docker-php-ext-configure gd \
  --with-freetype=/usr/include/ \
  --with-jpeg=/usr/include/ \
  && docker-php-ext-install gd \
  \
  && docker-php-ext-configure zip \
  && docker-php-ext-install zip \
  \
  && chown -Rf www-data:www-data /var/www/html/public \
  && chmod -Rf 755 /var/www/html/public

RUN curl -LO "https://dl.k8s.io/release/$(curl -L -s https://dl.k8s.io/release/stable.txt)/bin/linux/amd64/kubectl" && \
  curl -LO "https://dl.k8s.io/$(curl -L -s https://dl.k8s.io/release/stable.txt)/bin/linux/amd64/kubectl.sha256" && \
  chmod u+x ./kubectl && install -o root -g root -m 0755 kubectl /usr/bin/kubectl \
  && rm -f /lib/systemd/system/multi-user.target.wants/* \
  /etc/systemd/system/*.wants/* \
  /lib/systemd/system/local-fs.target.wants/* \
  /lib/systemd/system/sockets.target.wants/*udev* \
  /lib/systemd/system/sockets.target.wants/*initctl* \
  /lib/systemd/system/sysinit.target.wants/systemd-tmpfiles-setup* \
  /lib/systemd/system/systemd-update-utmp* \
  && echo "pt_BR.UTF-8 UTF-8" > /etc/locale.gen && locale-gen pt_BR.UTF-8 \
  && update-locale LANG=pt_BR.UTF-8

ARG COMMIT_SHA
ARG VERSION
ENV TZ America/Fortaleza
ENV LANG pt_BR.UTF-8
ENV LC_CTYPE pt_BR.UTF-8
ENV LC_ALL pt_BR.UTF-8
ENV LANGUAGE pt_BR:pt:en
ENV APACHE_RUN_USER www-data
ENV APACHE_RUN_GROUP www-data

RUN curl -sS https://getcomposer.org/installer -o composer-setup.php \
  && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
  && composer self-update

RUN composer install --ignore-platform-reqs --no-interaction --no-progress --no-scripts --optimize-autoloader

RUN cp bash/apache/000-default.conf /etc/apache2/sites-available/000-default.conf && apachectl configtest

RUN adduser --no-create-home --disabled-password --shell /bin/bash --gecos "" --force-badname 3s \
  && echo "3s ALL=(ALL) NOPASSWD:ALL" >> /etc/sudoers

RUN php artisan config:clear \
  && php artisan config:cache \
  && php artisan route:cache \
  && chmod 777 -R /var/www/html/storage/ \
  && chown -Rf www-data:www-data /var/www/ \
  && a2enmod rewrite

# Stage 2 - Prod
FROM dev as production

ENV APP_ENV=production
ENV APP_DEBUG=false

RUN cp bash/php/conf.d/opcache.ini /usr/local/etc/php/conf.d/opcache.ini \
  && a2enmod rewrite \
  && ln -s /etc/apache2/sites-available/000-default.conf /etc/apache2/sites-enabled/default.conf \
  && echo "ServerName localhost" >> /etc/apache2/apache2.conf \
  && cp bash/k8s/health-check.sh / \
  && chmod +x /health-check.sh

COPY --from=dev /var/www/html /var/www/html

WORKDIR /var/www/html

RUN composer install --prefer-dist --no-interaction --no-dev \
  && chown -R www-data:www-data /var/www/html/storage \
  && chmod -R 775 /var/www/html/storage \
  && php artisan route:cache \
  && php artisan config:clear \
  && php artisan view:clear \
  && php artisan storage:link \
  && a2enmod rewrite

EXPOSE 80

LABEL \
  org.opencontainers.image.vendor="UNILAB" \
  org.opencontainers.image.title="Official 3S Docker image" \
  org.opencontainers.image.description="3S (Sistema de Solicitação de Servicos) " \
  org.opencontainers.image.version="${VERSION}" \
  org.opencontainers.image.url="https://3s.unilab.edu.br/" \
  org.opencontainers.image.source="http://dti-gitlab.unilab.edu.br/disir/piloto-ci-cd-stack-devops-disir-dti.git" \
  org.opencontainers.image.revision="${COMMIT_SHA}" \
  org.opencontainers.image.licenses="N/D" \
  org.opencontainers.image.author="Jeff Ponte" \
  org.opencontainers.image.company="Universidade da Integracao Internacional da Lusofonia Afro-Brasileira (UNILAB)" \
  org.opencontainers.image.maintainer="DTI/Unilab"