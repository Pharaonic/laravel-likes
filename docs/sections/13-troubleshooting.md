## Troubleshooting

### `The [likes] table already exists` when migrating

Another package, usually `pharaonic/laravel-users`, created a `likes` table. Set `pharaonic.likes.table` to another name before migrating, or move that table out of the way first.

### `like()` returns false the first time

The pair was already liked: `like()` returns whether this call created the like. Use `hasLiked()` for the current state. A `creating` listener on the `Like` model that returns false also makes `like()` return false.

### `The liker [App\User] is not saved`

A write was called with a model that isn't saved. Save it first, or check `$model->exists`.

### `Register a shorter alias with Relation::morphMap()`

The class name is longer than the 191 characters the type columns hold. Add a morph map alias for the model.

### `Illegal mix of collations` on MySQL

Your model's table and the likes table use different collations, and the model has a string key (UUID, ULID). Create both with the same collation, the `collation` of your database connection.

### `operator does not exist: bigint = character varying` on PostgreSQL

A query compares a model key with the string `likeable_id` column directly, for example a hand-written join. Go through the package relations (`likes()`, `givenLikes()`, `whereLikedBy()`, `withCount('likes')`), which compare the key as a string, or cast it yourself: `cast(posts.id as varchar) = likes.likeable_id`.

### Likes stay after deleting models

The models were deleted with a query (`Post::where(...)->delete()`), which skips model events, or `cleanup_on_delete` is false. See [Deletes and Soft Deletes](#deletion-and-soft-deletes) for the manual cleanup.

### Likes are missing after adding a morph map

Rows stored before the map hold class names, and the package now looks for the alias. Update those rows deliberately, type by type, for example `UPDATE likes SET likeable_type = 'post' WHERE likeable_type = 'App\\Post';`.
