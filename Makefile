
COMPOSER_OPTS := --optimize-autoloader
PHPCS_OPTS := -n -p --standard=vendor/jbboehr/coding-standard/JbboehrStandard/ruleset.xml \
	--report=full --ignore=tests/Spec/* bin src tests

cbf: vendor
	./vendor/bin/phpcbf $(PHPCS_OPTS)

clean:
	rm -rf docs reports

coverage: vendor
	./vendor/bin/phpunit --coverage-text --coverage-html=reports

cs: vendor
	./vendor/bin/phpcs $(PHPCS_OPTS)

docs: apigen.neon src/*
	rm -rf docs
	apigen generate
	@touch -c docs

phpunit: vendor tests/Spec
	./vendor/bin/phpunit

test: cs phpunit

tests/Spec: vendor spec/handlebars spec/mustache tests/Generator.php \
		tests/VMGenerator.php tests/CompilerGenerator.php
	php generate-tests.php
	@touch -c tests/Spec

vendor: composer.json composer.lock
	composer install $(COMPOSER_OPTS)
	@touch -c vendor

.PHONY: cbf clean coverage cs phpunit test
