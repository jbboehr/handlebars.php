#!/usr/bin/env bash

set -ex

case "$1" in
install_handlebars)
	#INSTALLED_HANDLEBARS_VERSION=`handlebarsc --version 2>&1 | awk '{ print $2 }'`
	#if [ ! -f $PREFIX/include/handlebars.h ] || [ "$INSTALLED_HANDLEBARS_VERSION" != "v$LIBHANDLEBARS_VERSION" ]; then
		git clone -b $LIBHANDLEBARS_VERSION https://github.com/jbboehr/handlebars.c handlebars-c --recursive
		cd handlebars-c
		./bootstrap
		./configure --prefix=$PREFIX
		make $MAKE_OPTS
		make install
		cd ..
		rm -Rf handlebars-c
	#fi
	;;

install_php_handlebars)
	#INSTALLED_PHP_HANDLEBARS_VERSION=`php -r 'echo phpversion("handlebars");'`
	rm -Rf php-handlebars
	git clone -b $PHP_HANDLEBARS_VERSION https://github.com/jbboehr/php-handlebars.git php-handlebars --recursive
	cd php-handlebars
	phpize
	./configure
	make $MAKE_OPTS
	cp modules/handlebars.so ..
	cd ..
	rm -Rf php-handlebars
	echo "extension=`pwd`/handlebars.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
	;;

phpunit)
	if [ "$COVERAGE" = "true" ]; then
		#./vendor/bin/phpunit --coverage-text --coverage-clover=coverage.clover
		mkdir coverage
		./vendor/bin/phpunit -dmemory_limit=512M --coverage-php coverage/coverage1.cov
		php -n -dzend_extension=xdebug.so -dextension=json.so -dmemory_limit=512M ./vendor/bin/phpunit --coverage-php coverage/coverage2.cov
		php -dmemory_limit=512M ./vendor/bin/phpcov merge --clover coverage.clover coverage
	else
		./vendor/bin/phpunit
	fi
	;;

after_success)
	if [ "$COVERAGE" = "true" ]; then
		mkdir -p build/logs/
		php vendor/bin/coveralls -x coverage.clover
		php vendor/bin/ocular code-coverage:upload --format=php-clover coverage.clover
	fi
	;;
esac
