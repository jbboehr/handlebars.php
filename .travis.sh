#!/bin/sh

# set -e

export DEBIAN_FRONTEND=noninteractive

case "$1" in
before_install)
    sudo rm -f /etc/apt/sources.list.d/*rabbit*
    sudo apt-get update -qq
    sudo apt-get install software-properties-common || sudo apt-get install python-software-properties || true
    sudo apt-add-repository -y ppa:jbboehr/handlebars
    sudo add-apt-repository -y ppa:ubuntu-toolchain-r/test
    sudo apt-add-repository -y ppa:jbboehr/ppa
    sudo apt-add-repository -y ppa:mandel/movie-tracker
    sudo apt-get update -qq
    ;;
install)
    if [ "$TRAVIS_PHP_VERSION" != "hhvm" ]; then
        sudo apt-get install -qq libhandlebars-dev libtalloc-dev
        git clone https://github.com/jbboehr/php-handlebars.git
        cd php-handlebars
        phpize
        ./configure
        make 
        sudo make install
        echo "extension=handlebars.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
        cd ..
    else
        sudo apt-get update -qq
        sudo apt-get install -qq libhandlebars-dev libtalloc-dev g++-4.8 gcc-4.8 libboost1.49-dev libgoogle-glog-dev libjemalloc-dev
        sudo DEBIAN_FRONTEND=noninteractive apt-get install -o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confnew" -y -q hhvm hhvm-dev
        sudo update-alternatives --install /usr/bin/g++ g++ /usr/bin/g++-4.8 90
        git clone https://github.com/jbboehr/hhvm-handlebars.git
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
    composer install
    php generate-tests.php
    ;;
after_success)
    if [ "$TRAVIS_PHP_VERSION" != "hhvm" ] && [ "$TRAVIS_PHP_VERSION" != "7" ]; then
        php vendor/bin/ocular code-coverage:upload --format=php-clover coverage.clover
    fi
    ;;
esac
