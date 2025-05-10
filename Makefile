build:
	docker compose build --no-cache

up:
	docker compose up -d

down:
	docker compose down

logs:
	docker compose logs -f

ps:
	docker compose ps

shell:
	docker compose exec app bash

composer:
	docker compose exec app composer $(command)

artisan:
	docker compose exec app php artisan $(command)

test:
	docker compose exec app php artisan test
