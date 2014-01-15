COMPOSER_BIN := composer
PHPUNIT_BIN := ./vendor/bin/phpunit
BUGFREE_BIN := ./bin/bugfree
PHPCS_BIN := ./vendor/bin/phpcs --standard=vendor/vektah/psr2

default: test

depends: vendor

cleandepends: cleanvendor vendor

vendor: composer.json
	$(COMPOSER_BIN) --dev update
	touch vendor

cleanvendor:
	rm -rf composer.lock
	rm -rf vendor

lint: depends
	echo " --- Lint ---"
	$(BUGFREE_BIN) -a lint src
	echo

phpcs:
	$(PHPCS_BIN) src bin

test: lint depends phpcs
	echo " --- Unit tests ---"
	$(PHPUNIT_BIN)
	echo

.SILENT:
