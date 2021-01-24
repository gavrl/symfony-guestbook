.PHONY: tests

init: up serve watch consume

up:
	docker-compose up -d

down:
	docker-compose down --remove-orphans

tests:
	symfony console doctrine:fixtures:load -n
	symfony php bin/phpunit

serve:
	symfony server:start -d

watch:
	symfony run -d yarn encore dev --watch

consume:
	symfony console messenger:consume async -vv

cache:
	php bin/console cache:clear