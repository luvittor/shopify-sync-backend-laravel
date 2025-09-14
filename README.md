# Shopify Sync Backend

[![PHP](https://img.shields.io/badge/PHP-8.2-blue)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12.0-red)](https://laravel.com/)
[![SQLite](https://img.shields.io/badge/DB-SQLite-lightgrey)](https://www.sqlite.org/)

A Laravel backend service to synchronize products from Shopify.
This project was developed as part of a **technical assessment**: synchronize Shopify products using **Laravel** for the backend and **Vue.js** for the frontend (separate repo).

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
4. Generate the application key with `php artisan key:generate`
5. Run migrations with `php artisan migrate`
6. Run the application with `php artisan serve`
7. Access the API at `http://localhost:8000/api/v1/products` or use the Postman collection in `docs/postman/`.

## API Endpoints

* **GET** `/api/v1/products` – List all local products with count
* **POST** `/api/v1/products/sync` – Synchronize products from Shopify to local database
* **DELETE** `/api/v1/products/clear` – Clear all local products

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

## Frontend

The Vue.js frontend for this assessment is implemented in a **separate repository**.
It consumes this backend API for synchronization and product listing.

## Architecture Decisions

I considered applying CQRS/DDD, but kept the project intentionally **simple and clear**.

* `ProductController` manages product-related HTTP requests.
* `ShopifyService` handles communication with the Shopify API.
* `ProductRepository` abstracts database access.
* `Product` model represents the product entity.

## Credits

Developed by **Luciano Vettoretti**

* [LinkedIn](https://www.linkedin.com/in/luvittor/)
