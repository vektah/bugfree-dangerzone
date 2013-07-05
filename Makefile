COMPOSER_BIN := composer

depends: vendor

cleandepends: cleanvendor vendor

vendor: composer.json
	@$(COMPOSER_BIN) --dev update
	@touch vendor

cleanvendor:
	@rm -rf composer.lock
	@rm -rf vendor
