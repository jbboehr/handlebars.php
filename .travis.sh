#!/usr/bin/env bash

set -ex

export PREFIX=$HOME/build
export PATH="$PREFIX/bin:$PATH"
export CFLAGS="-L$PREFIX/lib"
export CPPFLAGS="-I$PREFIX/include"
export PKG_CONFIG_PATH="$PREFIX/lib/pkgconfig"

case "$1" in
install_check)
	if [ ! -f $PREFIX/include/check.h ]; then
		wget http://downloads.sourceforge.net/project/check/check/0.9.14/check-0.9.14.tar.gz
		tar xfv check-0.9.14.tar.gz
		cd check-0.9.14
		./configure --prefix=$PREFIX
		make
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
		make
		make install
		cd ..
		rm -Rf bison-3.0.2 bison-3.0.2.tar.gz
	fi
	;;

install_handlebars)
	if [ ! -f $PREFIX/include/handlebars.h ]; then
		git clone -b v$LIBHANDLEBARS_VERSION https://github.com/jbboehr/handlebars.c handlebars-c --recursive
		cd handlebars-c
		./bootstrap
		./configure --prefix=$PREFIX
		make install
		cd ..
		rm -Rf handlebars-c
	fi
	;;

install_php_handlebars)
	rm -Rf php-handlebars
	git clone -b v$PHP_HANDLEBARS_VERSION https://github.com/jbboehr/php-handlebars.git php-handlebars --recursive
	cd php-handlebars
	phpize
	./configure
	make
	cp modules/handlebars.so ..
	cd ..
	rm -Rf php-handlebars
	echo "extension=`pwd`/handlebars.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
	;;

after_success)
	if [ "$TRAVIS_PHP_VERSION" != "7" ]; then
		php vendor/bin/ocular code-coverage:upload --format=php-clover coverage.clover
	fi
	;;
esac

