.DEFAULT_GOAL:=help

REGEX = '(?<=\DB_VOLUME_NAME=)[a-zA-Z0-9\._-]*'
VOLUME := $(shell cat docker/.env | grep -oP ${REGEX})

.PHONY: build
build:
	docker-compose build

.PHONY: up
up:
	docker-compose up -d

.PHONY: down
down:
	docker-compose down

.PHONY: logs
logs:
	docker-compose logs -f

.PHONY: bash
bash:
	docker exec -ti php-presthubot-container /bin/bash

.PHONY: watch
watch:
	docker exec -ti php-presthubot-container npm run watch

.PHONY: dockerstart
dockerstart:
	sudo chmod 666 /var/run/docker.sock

.PHONY: dockerkill
dockerkill:
	docker kill $(docker ps -q)