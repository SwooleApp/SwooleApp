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
test:


stat-analise:
	./vendor/bin/phpstan analyse src -l 7
check:
	./vendor/bin/phpstan analyse src -l 7
	$(DOCKER_RUN) vendor/bin/phpunit --testdox

