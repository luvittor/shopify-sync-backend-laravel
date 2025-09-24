# Shopify Sync Laravel Backend

[![PHP](https://img.shields.io/badge/PHP-8.2-blue)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12.0-red)](https://laravel.com/)
[![SQLite](https://img.shields.io/badge/DB-SQLite-lightgrey)](https://www.sqlite.org/)
[![deepwiki](https://img.shields.io/badge/deepwiki-article-blue)](https://deepwiki.com/luvittor/shopify-sync-backend-laravel)

A Laravel backend service to synchronize products from Shopify.

This project was developed as part of a **technical assessment**: synchronize Shopify products using **Laravel** for the backend and **Vue.js** for the frontend (visit the repo here: [shopify-sync-frontend-vue](https://github.com/luvittor/shopify-sync-frontend-vue)).

Tests run against mocked Shopify responses, so you can work locally without real API credentials.

## Requirements

* PHP 8.2 or higher
* Composer
* Laravel 12.0
* SQLite (included by default) or another supported database

## Features

* Synchronize products from Shopify API
* List local products with pagination
* Clear local products

## Installation

1. Clone the repository
2. Run `composer install`
3. Copy `.env.example` to `.env` and fill in Shopify credentials:
   * `SHOPIFY_SHOP` – Your Shopify store subdomain (without `.myshopify.com`)
   * `SHOPIFY_ACCESS_TOKEN` – Your Shopify Admin API access token
   * `SHOPIFY_API_VERSION` – Shopify API version (default: 2025-07)
   * `CORS_ALLOWED_ORIGINS` – Allowed origins for CORS ([see below](#env-cors_allowed_origins))
4. Generate the application key with `php artisan key:generate`
5. Run migrations with `php artisan migrate`
6. Run the application with `php artisan serve`
7. Access the API at `http://localhost:8000/api/v1/products` or use the Postman collection in `docs/postman/`.

### Env CORS_ALLOWED_ORIGINS

Set `CORS_ALLOWED_ORIGINS` with your frontend URLs separated by commas, e.g. `https://www.example.com,https://example.com` or `*` to allow all origins (recommended only for development, **NOT production**).

Note: If you change `CORS_ALLOWED_ORIGINS` after initial setup, you may need to clear the configuration cache to apply the changes:

```bash
php artisan config:clear
php artisan cache:clear
```

## API Endpoints

* **GET** `/api/v1/products` – List all local products with count and pagination
* **POST** `/api/v1/products/sync` – Fetch products from Shopify and upsert them locally
* **DELETE** `/api/v1/products/clear` – Remove every product from the local catalogue

## CLI Commands

### Shopify Sync Command

You can also synchronize products using the Artisan command line interface:

```bash
php artisan shopify:sync
```

**Additional options:**
* `-v, --verbose` – Show detailed error information if sync fails
* `-q, --quiet` – Suppress all output except errors

## Database Schema

| Column       | Type            | Description                            |
| ------------ | --------------- | -------------------------------------- |
| `id`         | Primary Key     | Auto-incrementing ID                   |
| `title`      | String          | Product title/name                     |
| `price`      | Decimal(10,2)   | Product price with 2 decimal places    |
| `stock`      | Integer         | Available stock quantity               |
| `shopify_id` | String (Unique) | Shopify product ID for synchronization |
| `created_at` | Timestamp       | Record creation time                   |
| `updated_at` | Timestamp       | Record last update time                |

The `shopify_id` field ensures products can be properly synchronized and updated without duplicates.

## Testing

Tests rely on mocked HTTP responses so no Shopify credentials are needed.

Run the whole suite with:

```bash
php artisan test
```

Tests are run automatically in CI/CD pipelines in GitHub Actions.

## Architecture Decisions

I considered applying CQRS/DDD patterns, but kept the project intentionally **simple and clear** for this technical assessment, prioritizing readability and maintainability over complex architectural patterns.

### Component Responsibilities

* **`ProductService`** – Domain façade that coordinates pagination, clearing, and Shopify sync workflows
* **`ShopifyService`** – Thin API client responsible for fetching products from Shopify with automatic pagination support
* **`ProductRepository`** – Encapsulates database reads/writes for the `products` table
* **`ProductController`** – HTTP entry point delegating work to the `ProductService`
* **`SyncShopifyProducts` Command** – CLI entry point that calls into the same service layer as the API
* **`Product` Model** – Eloquent representation of the persisted product rows

### Data Flow

1. **HTTP entry** → `ProductController` → `ProductService` → (`ShopifyService` ⇄ Shopify API, `ProductRepository`) → `Product` Model
2. **CLI entry** → `SyncShopifyProducts` → `ProductService` → (`ShopifyService` ⇄ Shopify API, `ProductRepository`) → `Product` Model

### GitHub Actions

GitHub Actions workflows are included to deploy, test and audit the application:
* [audit.yml](.github/workflows/audit.yml)
* [deploy.yml](.github/workflows/deploy.yml)
* [tests.yml](.github/workflows/tests.yml)

## Credits

Created for technical assessment by **Luciano Vettoretti**.

* [LinkedIn](https://www.linkedin.com/in/luvittor/)
