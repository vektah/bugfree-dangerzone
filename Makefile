COMPOSER_BIN := composer
PHPUNIT_BIN := ./vendor/bin/phpunit
BUGFREE_BIN := ./bin/bugfree

depends: vendor

cleandepends: cleanvendor vendor

vendor: composer.json
	@$(COMPOSER_BIN) --dev update
	@touch vendor

cleanvendor:
	@rm -rf composer.lock
	@rm -rf vendor

lint: depends
	@echo " --- Lint ---"
	@$(BUGFREE_BIN) lint src
	@echo


test: lint depends
	@echo " --- Unit tests ---"
	@$(PHPUNIT_BIN)
	@echo
