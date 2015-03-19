
composer.phar:
	@[ ! -f composer.phar ] && [ ! -z `which composer` ] && ln -s `which composer` composer.phar || true
	@[ ! -f composer.phar ] && [ ! -z `which composer.phar` ] && ln -s `which composer.phar` composer.phar || true
	@[ ! -f composer.phar ] && exit 1 || true

vendor: composer.phar
	./composer.phar install

tests/Spec:
	php generate-tests.php

test: vendor tests/Spec
	@./vendor/bin/phpunit

