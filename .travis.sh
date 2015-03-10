#!/bin/sh

# set -e

sudo apt-get update -qq
sudo apt-get install software-properties-common || sudo apt-get install python-software-properties || true
sudo apt-add-repository -y ppa:jbboehr/handlebars
sudo add-apt-repository -y ppa:ubuntu-toolchain-r/test
sudo apt-add-repository -y ppa:jbboehr/ppa
sudo apt-add-repository -y ppa:mandel/movie-tracker
sudo apt-get update -qq

if [ "$TRAVIS_PHP_VERSION" != "hhvm" ]; then
    sudo apt-get install -qq libhandlebars-dev libtalloc-dev
    git clone https://github.com/jbboehr/php-handlebars.git
    cd php-handlebars
    phpize
    ./configure
    make 
    sudo make install
    echo "extension=handlebars.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
else
    sudo apt-get update -qq
    sudo apt-get install -qq libhandlebars-dev libtalloc-dev hhvm hhvm-dev g++-4.8 gcc-4.8 libboost1.49-dev libgoogle-glog-dev libjemalloc-dev
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

php generate-tests.php
