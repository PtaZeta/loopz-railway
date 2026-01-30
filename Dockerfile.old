FROM php:8.2-fpm


RUN apt update && apt install -y \

    lsb-release \

    ca-certificates \

    apt-transport-https \

    software-properties-common \

    gnupg \

    wget \

    unzip \

    libzip-dev \

    libpng-dev \

    libjpeg-dev \

    libonig-dev \

    libpq-dev \

    zip \

    nodejs \

    npm \

    git \

    curl \

    procps \

    grep \

    && rm -rf /var/lib/apt/lists/*


RUN mkdir -p /etc/apt/keyrings/

RUN wget -O /etc/apt/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg

RUN echo "deb [signed-by=/etc/apt/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/sury-php.list

RUN apt update


RUN docker-php-ext-configure pgsql --with-pgsql=/usr/bin/pg_config \

    && docker-php-ext-install pdo pgsql pdo_pgsql gd exif pcntl bcmath zip opcache mbstring


# Esta sección se ha simplificado para evitar el error de 'cp'

RUN PHP_INFO_OUTPUT=$(php -i) && \

    PHP_EXT_DIR=$(echo "$PHP_INFO_OUTPUT" | grep "^extension_dir" | sed -E 's/^extension_dir => (.*) => .*/\1/') && \

    echo "extension=pdo_pgsql.so" > /usr/local/etc/php/conf.d/20-pdo_pgsql.ini && \

    echo "extension=pgsql.so" > /usr/local/etc/php/conf.d/20-pgsql.ini


RUN php -m | grep -q "pdo_pgsql" || (echo "ERROR: pdo_pgsql NO CARGADO por PHP CLI durante la construcción!" && exit 1) && \

    php -m | grep -q "pgsql" || (echo "ERROR: pgsql NO CARGADO por PHP CLI durante la construcción!" && exit 1)


COPY --from=composer:latest /usr/bin/composer /usr/bin/composer


WORKDIR /var/www/html


COPY . .


RUN composer install --no-dev --optimize-autoloader


COPY .env .


RUN chown -R www-data:www-data storage bootstrap/cache


EXPOSE 9000


CMD ["php-fpm"] 
