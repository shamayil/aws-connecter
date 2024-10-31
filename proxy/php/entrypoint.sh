#!/bin/bash

# Define the base path and PHP binary
BASE_PATH="/var/www/html"
PHP_BIN="/usr/local/bin/php"

# Navigate to the application directory
cd $BASE_PATH

# Clear and cache various Laravel caches
for cmd in cache:clear route:clear view:clear config:clear view:cache config:cache; do
    $PHP_BIN artisan $cmd
done

######### Start scheduler ##########
echo "[INFO] Start scheduler"
touch "$BASE_PATH/cron.log"

# Add the cron job to the crontab
(crontab -l 2>/dev/null; echo "* * * * * $PHP_BIN $BASE_PATH/artisan schedule:run >> $BASE_PATH/cron.log 2>&1") | crontab -

# Start the cron daemon
crond -l 2 -b

# Start PHP-FPM
docker-php-entrypoint php-fpm
