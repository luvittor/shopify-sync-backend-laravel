# Shopify Sync Laravel Backend

[![PHP](https://img.shields.io/badge/PHP-8.2-blue)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12.0-red)](https://laravel.com/)
[![SQLite](https://img.shields.io/badge/DB-SQLite-lightgrey)](https://www.sqlite.org/)

A Laravel backend service to synchronize products from Shopify.

This project was developed as part of a **technical assessment**: synchronize Shopify products using **Laravel** for the backend and **Vue.js** for the frontend (visit the repo here: [shopify-sync-frontend-vue](https://github.com/luvittor/shopify-sync-frontend-vue)).

Tests don’t need Shopify and simulate the API.

## Requirements

* PHP 8.2 or higher
* Composer
* Laravel 12.0
* SQLite (included by default) or another supported database

## Features

* Product synchronization from remote (Shopify) to local database
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

* **GET** `/api/v1/products` – List all local products with count
* **POST** `/api/v1/products/sync` – Synchronize products from Shopify to local database
* **DELETE** `/api/v1/products/clear` – Clear all local products

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

Tests simulate Shopify API responses and don’t require real credentials.

Run with:

```bash
php artisan test
```

Tests are run automatically in CI/CD pipelines in GitHub Actions.

## Architecture Decisions

I considered applying CQRS/DDD patterns, but kept the project intentionally **simple and clear** for this technical assessment, prioritizing readability and maintainability over complex architectural patterns.

### Component Responsibilities:

* **`SyncShopifyProducts` Command** - CLI interface that delegates to `ShopifyService` for synchronization
* **`ProductController`** - Handles HTTP requests and coordinates business operations related to products
* **`ShopifyService`** - Manages Shopify API communication and orchestrates data synchronization
* **`ProductRepository`** - Abstracts database operations and provides a clean data access layer
* **`Product` Model** - Represents the product entity with Eloquent ORM features

### Data Flow:
1. HTTP requests → `ProductController` → `ShopifyService` → `ProductRepository` → `Product` Model
2. CLI commands → `SyncShopifyProducts` → `ShopifyService` → `ProductRepository` → `Product` Model

This layered approach separates concerns while maintaining simplicity, making the codebase easy to understand and extend.

### GitHub Actions

GitHub Actions workflows are included to deploy, test and audit the application:
* [audit.yml](.github/workflows/audit.yml)
* [deploy.yml](.github/workflows/deploy.yml)
* [tests.yml](.github/workflows/tests.yml)

## Credits

Created for technical assessment by **Luciano Vettoretti**.

* [LinkedIn](https://www.linkedin.com/in/luvittor/)
