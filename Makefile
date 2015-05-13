
PHPCS_OPTS := -n -p --standard=vendor/jbboehr/coding-standard/JbboehrStandard/ruleset.xml \
	--report=full --tab-width=4 --encoding=utf-8 --ignore=tests/Spec/* src tests

cbf: vendor
	./vendor/bin/phpcbf $(PHPCS_OPTS)

clean:
	rm -rf docs reports

coverage: vendor
	./vendor/bin/phpunit --coverage-text --coverage-html=reports

cs: vendor
	./vendor/bin/phpcs $(PHPCS_OPTS)

docs:
	apigen generate

phpunit: vendor tests/Spec
	./vendor/bin/phpunit

test: cs phpunit

tests/Spec:
	php generate-tests.php

vendor: 
	composer install --optimize-autoloader

.PHONY: cbf clean coverage cs phpunit test

