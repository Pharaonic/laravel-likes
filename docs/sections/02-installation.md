## Installation

Install the package with Composer and create its table.

### Requirements

- PHP 8.1, 8.2 or 8.3
- Laravel 10
- MySQL, MariaDB, PostgreSQL or SQLite

### Composer Installation

```bash title="Terminal" no-line-numbers
composer require pharaonic/laravel-likes
```

The service provider is registered automatically.

### Run Migrations

The package loads its migration automatically, so the `likes` table is created with your other migrations:

```bash title="Terminal" no-line-numbers
php artisan migrate
```

To change the migration before it runs, publish it into `database/migrations`:

```bash title="Terminal" no-line-numbers
php artisan vendor:publish --tag=likes-migrations
```

The published file keeps the package's file name, `2026_11_04_000001_create_pharaonic_likes_table.php`, so Laravel runs your copy instead of the package's, never both.

:::warning Existing likes table
The migration stops with an error when a `likes` table already exists, for example one created by `pharaonic/laravel-users`. Convert that table first, as [Upgrading from Laravel Users](#upgrading-from-laravel-users) describes, or set another table name.
:::

### Publish Configuration

Publish the configuration only when you need another table name or want to turn off cleanup on delete:

```bash title="Terminal" no-line-numbers
php artisan vendor:publish --tag=likes-config
```

The file is published to `config/pharaonic/likes.php`.

:::success Installation Complete
You're all set! Add `CanLike` and `HasLikes` to your models, as [Basic Usage](#basic-usage) shows.
:::
