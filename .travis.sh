#!/usr/bin/env bash

set -ex

export PREFIX=$HOME/build
export PATH="$PREFIX/bin:$PATH"
export CFLAGS="-L$PREFIX/lib"
export CPPFLAGS="-I$PREFIX/include"
export PKG_CONFIG_PATH="$PREFIX/lib/pkgconfig"
#export MAKE_OPTS="-j`nproc`"

case "$1" in
install_check)
	if [ ! -f $PREFIX/include/check.h ]; then
		wget http://downloads.sourceforge.net/project/check/check/0.9.14/check-0.9.14.tar.gz
		tar xfv check-0.9.14.tar.gz
		cd check-0.9.14
		./configure --prefix=$PREFIX
		make $MAKE_OPTS
		make install
		cd ..
		rm -Rf check-0.9.14.tar.gz check-0.9.14
	fi
	;;

install_bison)
	if [ ! -f $PREFIX/bin/bison ]; then
		wget http://gnu.mirror.iweb.com/bison/bison-3.0.2.tar.gz
		tar xfv bison-3.0.2.tar.gz
		cd bison-3.0.2
		./configure --prefix=$PREFIX
		make $MAKE_OPTS
		make install
		cd ..
		rm -Rf bison-3.0.2 bison-3.0.2.tar.gz
	fi
	;;

install_handlebars)
	INSTALLED_HANDLEBARS_VERSION=`handlebarsc --version 2>&1 | awk '{ print $2 }'`
	if [ ! -f $PREFIX/include/handlebars.h ] || [ "$INSTALLED_HANDLEBARS_VERSION" != "v$LIBHANDLEBARS_VERSION" ]; then
		git clone -b v$LIBHANDLEBARS_VERSION https://github.com/jbboehr/handlebars.c handlebars-c --recursive
		cd handlebars-c
		./bootstrap
		./configure --prefix=$PREFIX
		make $MAKE_OPTS
		make install
		cd ..
		rm -Rf handlebars-c
	fi
	;;

install_php_handlebars)
	#INSTALLED_PHP_HANDLEBARS_VERSION=`php -r 'echo phpversion("handlebars");'`
	rm -Rf php-handlebars
	git clone -b v$PHP_HANDLEBARS_VERSION https://github.com/jbboehr/php-handlebars.git php-handlebars --recursive
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
		./vendor/bin/phpunit --coverage-text --coverage-clover=coverage.clover
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
