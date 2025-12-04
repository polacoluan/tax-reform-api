# Docker o mais parecido possível com o servidor
FROM php:7.4-cli

WORKDIR /app

RUN apt-get update && apt-get install -y \
    wget \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip

# ionCube Loader para PHP 7.4
RUN wget https://downloads.ioncube.com/loader_downloads/ioncube_loaders_lin_x86-64.zip \
    && unzip ioncube_loaders_lin_x86-64.zip \
    && mv ioncube/ioncube_loader_lin_7.4.so /usr/local/lib/php/extensions/no-debug-non-zts-20190902/ \
    && echo "zend_extension=ioncube_loader_lin_7.4.so" > /usr/local/etc/php/conf.d/00-ioncube.ini \
    && rm -rf ioncube ioncube_loaders_lin_x86-64.zip

# Copia a app (vai ser sobrescrita pelo volume, mas tudo bem)
COPY . /app

EXPOSE 8000

# Servidor embutido do PHP
CMD ["php", "-S", "0.0.0.0:8000", "-t", "/app"]
