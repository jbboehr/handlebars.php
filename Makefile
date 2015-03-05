
composer.phar:
	@[ ! -f composer.phar ] && [ ! -z `which composer` ] && ln -s `which composer` composer.phar
	@[ ! -f composer.phar ] && [ ! -z `which composer.phar` ] && ln -s `which composer.phar` composer.phar
	@[ ! -f composer.phar ] && exit 1

vendor: composer.phar
	./composer install

tests/Spec:
	php generate-tests.php

test: vendor tests/Spec
	@./vendor/bin/phpunit

