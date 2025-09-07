PHP_VERSION := 8.3
SWOOLE_VERSION := 6.0
IMAGE_NAME := phpswoole/swoole:$(SWOOLE_VERSION)-php$(PHP_VERSION)
CONTAINER_NAME := swoole-app-tests
WORKDIR := /app
DOCKER_RUN := docker run --rm -it \
	--name $(CONTAINER_NAME) \
	-v $(PWD):$(WORKDIR) \
	-w $(WORKDIR) \
	$(IMAGE_NAME)
DOCKER_RUN_NOT_IT := docker run --rm \
	--name $(CONTAINER_NAME) \
	-v $(PWD):$(WORKDIR) \
	-w $(WORKDIR) \
	$(IMAGE_NAME)
test:
	$(DOCKER_RUN) vendor/bin/phpunit --testdox
stat-analise:
	./vendor/bin/phpstan analyse src -l 7
check:
	./vendor/bin/phpstan analyse src -l 7
	$(DOCKER_RUN_NOT_IT) vendor/bin/phpunit --testdox

