.DEFAULT_GOAL:=help

REGEX = '(?<=\DB_VOLUME_NAME=)[a-zA-Z0-9\._-]*'
VOLUME := $(shell cat docker/.env | grep -oP ${REGEX})

.PHONY: cc
cc:
	php bin/console cache:clear

.PHONY: phpcsfixer
phpcsfixer:
	php ./vendor/bin/php-cs-fixer fix

.PHONY: phpstan
phpstan:
	php ./vendor/bin/phpstan analyse src tests --memory-limit=-1 --level=3

.PHONY: watch
watch:
	npm run watch