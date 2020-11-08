up:
	docker-compose up -d

down:
	docker-compose down --remove-orphans

tests:
	symfony console doctrine:fixtures:load -n
	symfony php bin/phpunit

.PHONY: tests