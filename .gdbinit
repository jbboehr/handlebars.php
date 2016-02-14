file ~/.phpenv/versions/5.6.18/bin/php
set args \
    -c $PWD/php.ini \
    -d "extension=$PWD/../php-handlebars/modules/handlebars.so" \
    -n ./vendor/bin/phpunit $*
