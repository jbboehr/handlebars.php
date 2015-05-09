
COMPOSER := $(shell which composer || which composer.phar)

clean:
	rm -rf docs reports

composer.phar:
	@if [ -z "$(COMPOSER)" ]; then wget https://getcomposer.org/composer.phar; chmod +x composer.phar; fi
	@if [ ! -z "$(COMPOSER)" ]; then ln -s $(COMPOSER) composer.phar; fi

coverage: vendor
	./vendor/bin/phpunit --coverage-text --coverage-html=reports

docs:
	apigen generate

php-cs-fixer: vendor
	./vendor/bin/php-cs-fixer fix src --fixers=-braces,-elseif,-parenthesis,-phpdoc_no_access,-phpdoc_no_empty_return,-phpdoc_params,-phpdoc_scalar,-phpdoc_separation,-phpdoc_short_description,-align_double_arrow,-align_equals,-return,long_array_syntax,ordered_use,newline_after_open_tag,concat_with_spaces

test: vendor tests/Spec
	./vendor/bin/phpunit

tests/Spec:
	php generate-tests.php

vendor: composer.phar
	./composer.phar install

.PHONY: clean coverage php-cs-fixer test

