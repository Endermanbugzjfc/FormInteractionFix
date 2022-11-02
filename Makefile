PHP = $(shell which php) -dphar.readonly=0
COMPOSER = hack/composer.phar

SUITE_TESTS = $(shell echo suitetest/cases/*)

POCKETMINE_VERSION = 4

PLUGIN_NAME = FormInteractionFix
PLUGIN_SOURCE_FILES = plugin.yml $(shell find src -type f)
PLUGIN_VIRIONS = hack/await-generator.phar hack/await-std.phar hack/libasynql.phar hack/rwlock.phar

.PHONY: all phpstan fmt debug/suite-mysql suitetest $(SUITE_TESTS)

default: phpstan hack/$(PLUGIN_NAME).phar

# > What are the `touch` for?
# Sofe > idk, I remember there was a case where somehow wget didn't update the mtime and it redownloads every time

# Downloads:
hack/composer.phar: Makefile
	cd dev && wget -O - https://getcomposer.org/installer | $(PHP)

vendor: $(COMPOSER) composer.json composer.lock
	$(PHP) $(COMPOSER) install --optimize-autoloader --ignore-platform-reqs
	touch $@

hack/await-generator.phar: Makefile
	wget -O $@ https://poggit.pmmp.io/v.dl/SOF3/await-generator/await-generator/^3.4.2
	touch $@

hack/await-std.phar: Makefile
	wget -O $@ https://poggit.pmmp.io/v.dl/SOF3/await-std/await-std/^0.2.0
	touch $@

hack/FakePlayer.phar: Makefile
	wget -O $@ https://poggit.pmmp.io/r/146802
	touch $@

hack/ConsoleScript.php: Makefile
	wget -O $@ https://github.com/pmmp/DevTools/raw/master/src/ConsoleScript.php
	touch $@

# Tools:
phpstan: vendor
	$(PHP) vendor/bin/phpstan analyze

fmt: $(shell find src -type f) .php-cs-fixer.php vendor
	$(PHP) vendor/bin/php-cs-fixer fix $$EXTRA_FLAGS

hack/$(PLUGIN_NAME).phar: $(PLUGIN_SOURCE_FILES) hack/ConsoleScript.php $(PLUGIN_VIRIONS)
	$(PHP) hack/ConsoleScript.php --make plugin.yml,src --out $@

# 	for file in $(PLUGIN_VIRIONS); do $(PHP) $$file $@ SOFe\\$(PLUGIN_NAME)\\Virions\\$$(tr -dc A-Za-z </hack/urandom | head -c 8)\\ ; done
	for file in $(PLUGIN_VIRIONS); do $(PHP) $$file ; done

# Tests:
hack/SuiteTester.phar: suitetest/plugin/plugin.yml \
	$(shell find suitetest/plugin/src -type f) \
	hack/ConsoleScript.php \
	hack/await-generator.phar hack/await-std.phar
	$(PHP) hack/ConsoleScript.php --make plugin.yml,src --relative suitetest/plugin/ --out $@
	$(PHP) hack/await-generator.phar $@ SOFe\\SuiteTester\\Virions\\$(shell tr -dc A-Za-z </hack/urandom | head -c 8)\\
	$(PHP) hack/await-std.phar $@ SOFe\\SuiteTester\\Virions\\$(shell tr -dc A-Za-z </hack/urandom | head -c 8)\\

suitetest: $(SUITE_TESTS)

$(SUITE_TESTS): hack/$(PLUGIN_NAME).phar hack/FakePlayer.phar hack/SuiteTester.phar
	$(eval CONTAINER_PREFIX := form-interaction-fix-suite-$(shell basename $@))
	docker network create $(CONTAINER_PREFIX)-network || true

	docker rm $(CONTAINER_PREFIX)-pocketmine || true
	docker create --name $(CONTAINER_PREFIX)-pocketmine \
		--network $(CONTAINER_PREFIX)-network \
		-e SUITE_TESTER_OUTPUT=/data/output.json \
		-u root \
		pmmp/pocketmine-mp:$(POCKETMINE_VERSION) \
		start-pocketmine --debug.level=2

	docker cp hack/FakePlayer.phar $(CONTAINER_PREFIX)-pocketmine:/plugins/FakePlayer.phar
	docker cp hack/SuiteTester.phar $(CONTAINER_PREFIX)-pocketmine:/plugins/SuiteTester.phar
	docker cp hack/$(PLUGIN_NAME).phar $(CONTAINER_PREFIX)-pocketmine:/plugins/$(PLUGIN_NAME).phar
	docker cp $@/data $(CONTAINER_PREFIX)-pocketmine:/
	docker cp suitetest/shared/data $(CONTAINER_PREFIX)-pocketmine:/

	docker start -ia $(CONTAINER_PREFIX)-pocketmine

	test -d $@/output || mkdir $@/output/

	docker cp $(CONTAINER_PREFIX)-pocketmine:/data/output.json $@/output/output.json
	$(PHP) -r '$$file = $$argv[1]; $$contents = file_get_contents($$file); $$data = json_decode($$contents); $$ok = $$data->ok; if($$ok !== true) exit(1);' $@/output/output.json \
		|| (cat $@/output/output.json && exit 1)