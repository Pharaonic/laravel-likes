# Changelog

All notable changes to this project are documented here.

## 13.0.3 - Unreleased

### Fixed

- Inside a database transaction, `like()`, `likeBy()` and `toggleLike()` threw the unique index violation when another request had committed the same like after the transaction's first read (MySQL's REPEATABLE READ). They now find that like with a locking read and return false, as they do outside a transaction.

## 13.0.2 - 2026-11-04

### Changed

- The error for an existing likes table no longer points to an upgrade guide: it says to set `pharaonic.likes.table` to another name, or move the existing table out of the way.

## 13.0.1 - 2026-11-04

### Removed

- The "Upgrading from Laravel Users" section of the documentation, with the links and FAQ entry that pointed to it.

## 13.0.0 - 2026-11-04

First release of the Laravel 13 line, built from 12.0.0.

### Compatibility

- Laravel 13 on PHP 8.3, 8.4 or 8.5. PHP 8.2 is no longer supported on this line.
- The same API and behavior as 12.0.0.

## 12.0.0 - 2026-11-04

First release of the Laravel 12 line, built from 11.0.0.

### Compatibility

- Laravel 12 on PHP 8.2, 8.3, 8.4 or 8.5.
- The same API and behavior as 11.0.0.

## 11.0.0 - 2026-11-04

First release of the Laravel 11 line, built from 10.0.0.

### Compatibility

- Laravel 11 on PHP 8.2, 8.3 or 8.4. PHP 8.1 is no longer supported on this line.
- The same API and behavior as 10.0.0. The relations and scopes declare their generic types (`MorphMany<Like, $this>`, `MorphTo<Model, $this>`) for static analysis.

## 10.0.0 - 2026-11-04

First release of the Laravel 10 line, built from 9.0.0.

### Compatibility

- Laravel 10 on PHP 8.1, 8.2 or 8.3. PHP 8.0 is no longer supported on this line.
- The same API and behavior as 9.0.0.

## 9.0.0 - 2026-11-04

First release of the Laravel 9 line, built from 8.0.0.

### Compatibility

- Laravel 9.33+ on PHP 8.0, 8.1 or 8.2. Laravel 9.33 is the first 9.x release that requires a Carbon release (2.62.1+) working on PHP 8.2; with older ones, every date method throws a `TypeError` on PHP 8.2.
- The same API and behavior as 8.0.0.

## 8.0.0 - 2026-11-04

First release of the Laravel 8 line, built from 7.0.0.

### Compatibility

- Laravel 8.75+ on PHP 8.0 or 8.1.
- The same API and behavior as 7.0.0. With `Relation::enforceMorphMap()`, liking with an unmapped model throws Laravel's `ClassMorphViolationException` before anything is written.

## 7.0.0 - 2026-11-04

First release of the Laravel 7 line, built from 6.0.0.

### Compatibility

- Laravel 7.30+ on PHP 8.0.
- The same API and behavior as 6.0.0.

## 6.0.0 - 2026-11-04

### Added

- `CanLike` for the model that likes: `like()`, `unlike()`, `hasLiked()`, `toggleLike()`, and the `givenLikes()` relation.
- `HasLikes` for the model that is liked: `likeBy()`, `unlikeBy()`, `likedBy()`, the `likes()` relation, and the `whereLikedBy()` scope. A model can use both traits.
- The `Like` model with `liker()` and `likeable()` relations and `whereLiker()` and `whereLikeable()` scopes.
- A `likes` table whose unique index keeps each liker and likeable pair to one row, with string ids so integer, UUID, and ULID keys share it.
- `LikeCreated` and `LikeRemoved` events, dispatched only when a call inserts or deletes a like.
- Likes are deleted with the model that gave or received them, and kept when it is only soft deleted.
- `pharaonic.likes.table` and `pharaonic.likes.cleanup_on_delete` settings.

### Compatibility

- Laravel 6 on PHP 8.0.
- Replaces the likes of `pharaonic/laravel-users`, whose methods return different values and whose table needs converting first; the upgrade guide covers both.
