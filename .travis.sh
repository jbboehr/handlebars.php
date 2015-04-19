#!/bin/sh

# set -e

case "$1" in
before_install)
    sudo rm -f /etc/apt/sources.list.d/*rabbit*
    sudo travis_retry apt-get update -qq
    sudo travis_retry apt-get install software-properties-common python-software-properties
    sudo travis_retry apt-add-repository -y ppa:jbboehr/handlebars
    sudo travis_retry apt-add-repository -y ppa:ubuntu-toolchain-r/test
    sudo travis_retry apt-add-repository -y ppa:jbboehr/ppa
    sudo travis_retry apt-add-repository -y ppa:mandel/movie-tracker
    sudo travis_retry apt-get update -qq
    ;;
install)
    if [ "$TRAVIS_PHP_VERSION" != "hhvm" ]; then
        sudo travis_retry apt-get install -qq libhandlebars-dev libtalloc-dev
        travis_retry git clone https://github.com/jbboehr/php-handlebars.git
        cd php-handlebars
        phpize
        ./configure
        make 
        sudo make install
        echo "extension=handlebars.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
        cd ..
    else
        sudo travis_retry apt-get update -qq
        sudo travis_retry apt-get install -qq libhandlebars-dev libtalloc-dev hhvm hhvm-dev g++-4.8 gcc-4.8 libboost1.49-dev libgoogle-glog-dev libjemalloc-dev
        sudo update-alternatives --install /usr/bin/g++ g++ /usr/bin/g++-4.8 90
        travis_retry git clone https://github.com/jbboehr/hhvm-handlebars.git
        cd hhvm-handlebars
        hphpize
        cmake .
        make
        sudo make install
        echo "hhvm.dynamic_extensions[handlebars]=`pwd`/handlebars.so" | sudo tee -a /etc/hhvm/php.ini
        cd ..
    fi
    ;;
before_script)
    travis_retry composer install
    php generate-tests.php
    ;;
after_success)
    if [ "$TRAVIS_PHP_VERSION" != "hhvm" ]; then
        travis_retry wget https://scrutinizer-ci.com/ocular.phar
        php ocular.phar code-coverage:upload --format=php-clover coverage.clover
    fi
    ;;
esac
