# Changelog

All notable changes to this project are documented here.

## 8.0.2 - Unreleased

### Fixed

- Inside a database transaction, `like()`, `likeBy()` and `toggleLike()` threw the unique index violation when another request had committed the same like after the transaction's first read (MySQL's REPEATABLE READ). They now find that like with a locking read and return false, as they do outside a transaction.

## 8.0.1 - 2026-11-04

### Changed

- The error for an existing likes table no longer points to an upgrade guide: it says to set `pharaonic.likes.table` to another name, or move the existing table out of the way.

### Removed

- The "Upgrading from Laravel Users" section of the documentation, with the links and FAQ entry that pointed to it.

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
