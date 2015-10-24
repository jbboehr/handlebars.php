
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

xhprof: vendor
	php -d extension=xhprof.so bench.php $(BENCH_OPTS)
	php -S 127.0.0.1:1234 -t vendor/lox/xhprof/xhprof_html/

.PHONY: cbf clean coverage cs phpunit test
