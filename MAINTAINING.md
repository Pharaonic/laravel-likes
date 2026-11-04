# Maintaining This Package

This file records package-specific maintainer facts that are safe to keep in the public repository.

## Purpose

Polymorphic likes between any two Eloquent models: `CanLike` for the model that likes, `HasLikes` for the model that is liked, one `likes` table whose unique index keeps each pair to one row.

## Supported Laravel lines

One branch per Laravel major, from `6.x` to the newest major. Each line's PHP range, `illuminate/*` constraint, and tools follow the Pharaonic Laravel line table. `6.x` runs PHPUnit; later lines run Pest.

## Core library

None. Everything the package does is Eloquent glue.

## Public API invariants

Configuration keys (`pharaonic.likes.*`), publish tags, and registered Blade, Artisan, macro, and validation names are public API.

- Configuration: `pharaonic.likes.table` (default `likes`) and `pharaonic.likes.cleanup_on_delete` (default `true`).
- Publish tags: `pharaonic`, `laravel-likes`, `pharaonic-config`, `likes-config`, `pharaonic-migrations`, and `likes-migrations`.
- `CanLike`: `like()`, `unlike()`, `hasLiked()`, `toggleLike()`, `givenLikes()`. `HasLikes`: `likes()`, `likeBy()`, `unlikeBy()`, `likedBy()`, `scopeWhereLikedBy()`. Neither trait defines a method of the other, so a model can use both.
- `like()`, `likeBy()`, `unlike()`, and `unlikeBy()` return whether the call changed the table; `toggleLike()` returns the state it left. `hasLiked()` and `likedBy()` return false for an unsaved model; the writes throw `InvalidArgumentException` for one.
- `Models\Like` with `liker()`, `likeable()`, `scopeWhereLiker()`, `scopeWhereLikeable()`, and the columns `liker_type`, `liker_id`, `likeable_type`, `likeable_id`.
- `Events\LikeCreated` and `Events\LikeRemoved`, each with public `$like`, `$liker`, and `$likeable`, dispatched only when the call inserted or deleted the row.
- `Support\LikeManager` and `Relations\LikesMorphMany` are internal.

## Package-specific constraints

- The migration file name `2026_11_04_000001_create_pharaonic_likes_table.php` and its class `CreatePharaonicLikesTable` never change. The class is named because `6.x` supports Laravel below 8.37, and it is not `CreateLikesTable` so it never collides with the class `pharaonic/laravel-users` ships.
- Later schema changes go in new migrations, never in the released one.
- The migration refuses to run over an existing table (it may be the `laravel-users` table without the unique index).
- Uniqueness comes from the `{table}_unique_pair` index, never from a read before the write. A unique violation counts as "already liked" only when the pair's row can be read afterwards; otherwise the insert is retried, then the exception is rethrown.
- A deadlock (SQLSTATE `40001` or `40P01`, MySQL error 1213) on the insert or delete runs the statement again, up to three times, but only outside a transaction: inside one, the database already rolled the caller's transaction back.
- Ids are stored as strings (`varchar(64)`), so the likes relations compare the parent key as a string in `has()`, `whereHas()`, and `withCount()` (`LikesMorphMany`): PostgreSQL refuses `bigint = varchar` and `uuid = varchar`. MySQL uses `CONCAT()` rather than `CAST()`, which fails when the connection collation differs from the table's.
- Events are dispatched right after the write, inside the caller's transaction when there is one, because `ShouldDispatchAfterCommit` needs Laravel 10.30.
- `phpstan.neon.dist` also analyses `tests/Fixtures`: PHPStan analyses a trait only through a class that uses it.
- `ConcurrencyTest` runs separate PHP processes (`tests/Processes/worker.php`) against a shared database: a SQLite file by default, MySQL or PostgreSQL through the `LIKES_DB*` environment variables (`.github/workflows/databases.yml`).
- The published-migration test needs a process where the migration class was never declared. `6.x` runs it as a PHPUnit class in separate processes; Pest cannot, so later lines run `tests/Processes/published-migration.php` as a subprocess.

## Branch notes

Fixes land on the oldest affected line and are ported to every later line and `develop`.

## Documentation

https://pharaonic.dev/packages/laravel/likes

Keep this file factual and package-specific. General Pharaonic engineering policy belongs in the private engineering repository, not here.
