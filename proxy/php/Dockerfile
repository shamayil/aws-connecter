# Use the official PHP image with FPM and Alpine Linux
FROM php:8.3.10-fpm-alpine3.20

# Set environment variables
ENV PM_MAX_CHILDREN=1 \
    PM_START_SERVERS=1 \
    PM_MIN_SPARE_SERVERS=1 \
    PM_MAX_SPARE_SERVERS=1 \
    MEMORY_LIMIT=64M \
    MAX_EXECUTION_TIME=300 \
    UPLOAD_MAX_SIZE=16M \
    PM_MAX_REQUEST=500

# Set the timezone to Europe/London
ENV TZ=Europe/London

RUN apk add --no-cache tzdata \
    && cp /usr/share/zoneinfo/$TZ /etc/localtime \
    && echo $TZ > /etc/timezone \
    && apk del tzdata

# Install dependencies
RUN apk update && apk add --no-cache \
    dcron \
    sudo \
    curl \
    nano \
    git \
    gmp-dev \
    postgresql-dev \
    docker-cli \
    linux-headers \
    autoconf \
    g++ \
    make \
    libjpeg-turbo-dev \
    libpng-dev \
    freetype-dev \
    libxml2-dev \
    libzip-dev \
    bash \
    mysql  \
    mysql-client

# Install PHP extensions
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        mysqli \
        pdo_mysql \
        pdo_pgsql \
        soap \
        sockets \
        xml \
        gmp \
        zip \
    && docker-php-ext-install exif  # Install exif extension

# Install and configure Imagick extension
RUN apk add --no-cache imagemagick-dev \
    && pecl install imagick \
    && docker-php-ext-enable imagick

# Install Redis extension
RUN pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Modify the default PHP-FPM www.conf file
RUN sed -i "s/pm.max_children = 5/pm.max_children = ${PM_MAX_CHILDREN}/g" /usr/local/etc/php-fpm.d/www.conf \
    && sed -i "s/pm.start_servers = 2/pm.start_servers = ${PM_START_SERVERS}/g" /usr/local/etc/php-fpm.d/www.conf \
    && sed -i "s/pm.min_spare_servers = 1/pm.min_spare_servers = ${PM_MIN_SPARE_SERVERS}/g" /usr/local/etc/php-fpm.d/www.conf \
    && sed -i "s/pm.max_spare_servers = 3/pm.max_spare_servers = ${PM_MAX_SPARE_SERVERS}/g" /usr/local/etc/php-fpm.d/www.conf \
    && sed -i "s/;pm.max_requests = 500/pm.max_requests = ${PM_MAX_REQUEST}/g" /usr/local/etc/php-fpm.d/www.conf

# Memory and Upload Configurations (php-memlimit.ini)
RUN if [ ! -d "/usr/local/etc/php/conf-docker" ]; then \
        mkdir -p /usr/local/etc/php/conf-docker; \
    fi && \
    { \
        echo "memory_limit=${MEMORY_LIMIT}"; \
        echo "max_execution_time=${MAX_EXECUTION_TIME}"; \
        echo "upload_max_filesize=${UPLOAD_MAX_SIZE}"; \
        echo "post_max_size=${UPLOAD_MAX_SIZE}"; \
    } > /usr/local/etc/php/conf-docker/php-memlimit.ini

# FPM Configuration (www.conf)
RUN if [ ! -f "/usr/local/etc/php-fpm.d/www.conf" ]; then \
        touch /usr/local/etc/php-fpm.d/www.conf; \
    fi && \
    { \
        echo "php_admin_value[memory_limit] = ${MEMORY_LIMIT}"; \
        echo "php_value[memory_limit] = ${MEMORY_LIMIT}"; \
        echo "php_value[post_max_size] = ${UPLOAD_MAX_SIZE}"; \
        echo "php_value[upload_max_filesize] = ${UPLOAD_MAX_SIZE}"; \
        echo "php_value[max_input_time] = ${MAX_EXECUTION_TIME}"; \
        echo "php_value[max_execution_time] = ${MAX_EXECUTION_TIME}"; \
    } >> /usr/local/etc/php-fpm.d/www.conf

# Set up working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Add entrypoint script
ADD ./proxy/php/entrypoint.sh /

# Set permissions for storage and cron files
RUN mkdir -p storage \
    && chmod -R 777 storage

# Set permissions for the cron directory and create necessary files
RUN mkdir -p /var/run && touch /var/run/crond.pid \
    && chown root:root /var/run/crond.pid

# Set permissions for the entrypoint script
RUN if [ -f /entrypoint.sh ]; then \
    chmod +x /entrypoint.sh; \
    else \
    echo "Entrypoint script not found!"; \
    fi

# Ensure PHP-FPM config directory has correct permissions
RUN if [ -d /usr/local/etc/php-fpm.d ]; then \
    chown -R root:root /usr/local/etc/php-fpm.d; \
    else \
    echo "PHP-FPM config directory not found!"; \
    fi

# Remove composer.lock if it exists
RUN if [ -f composer.lock ]; then \
    rm -f composer.lock; \
    fi

# Install Composer dependencies
RUN COMPOSER_MIRROR_PATH_REPOS=1 composer install --prefer-dist --no-ansi --no-interaction --no-progress --optimize-autoloader --no-dev

# Expose port 9000 for PHP-FPM
EXPOSE 9000

# Set the entrypoint script
ENTRYPOINT ["sh", "/entrypoint.sh"]

# Command to run PHP-FPM
CMD ["php-fpm"]
