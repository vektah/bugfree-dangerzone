COMPOSER_BIN := composer
PHPUNIT_BIN := ./vendor/bin/phpunit

depends: vendor

cleandepends: cleanvendor vendor

vendor: composer.json
	@$(COMPOSER_BIN) --dev update
	@touch vendor

cleanvendor:
	@rm -rf composer.lock
	@rm -rf vendor

test: depends
	@$(PHPUNIT_BIN)
