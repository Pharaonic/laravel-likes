## API Reference

### `CanLike`

`Pharaonic\Laravel\Likes\Concerns\CanLike`, for the model that likes.

| Method | Returns | Description |
| --- | --- | --- |
| `like(Model $likeable)` | `bool` | Likes the model. True when this call created the like, false when it already existed. |
| `unlike(Model $likeable)` | `bool` | Removes the like. True when this call removed it, false when there was none. |
| `hasLiked(Model $likeable)` | `bool` | Whether this model likes the model. False when either is unsaved. |
| `toggleLike(Model $likeable)` | `bool` | Likes the model, or removes the like. Returns the new state: true when liked. |
| `givenLikes()` | `MorphMany` | The likes this model gave. |

### `HasLikes`

`Pharaonic\Laravel\Likes\Concerns\HasLikes`, for the model that is liked.

| Method | Returns | Description |
| --- | --- | --- |
| `likes()` | `MorphMany` | The likes this model received. |
| `likeBy(Model $liker)` | `bool` | Same as `$liker->like($this)`, for any liker model. |
| `unlikeBy(Model $liker)` | `bool` | Same as `$liker->unlike($this)`. |
| `likedBy(Model $liker)` | `bool` | Same as `$liker->hasLiked($this)`. |
| `whereLikedBy(Model $liker)` | scope | Keeps the models the liker likes: `Post::whereLikedBy($user)`. |

### `Like`

`Pharaonic\Laravel\Likes\Models\Like`.

| Member | Type | Description |
| --- | --- | --- |
| `liker()` | `MorphTo` | The model that gave the like. |
| `likeable()` | `MorphTo` | The model that received the like. |
| `whereLiker(Model $liker)` | scope | Likes given by the model. Matches nothing for an unsaved model. |
| `whereLikeable(Model $likeable)` | scope | Likes received by the model. Matches nothing for an unsaved model. |
| `id` | `int` | Primary key. |
| `liker_type`, `likeable_type` | `string` | `getMorphClass()` of each end, up to 191 characters. |
| `liker_id`, `likeable_id` | `string` | The key of each end as a string, up to 64 characters. |
| `created_at`, `updated_at` | `Carbon` | Timestamps. |

### Events

| Event | Properties | Dispatched |
| --- | --- | --- |
| `Pharaonic\Laravel\Likes\Events\LikeCreated` | `Like $like`, `Model $liker`, `Model $likeable` | After a call inserted a like. |
| `Pharaonic\Laravel\Likes\Events\LikeRemoved` | `Like $like`, `Model $liker`, `Model $likeable` | After a call deleted a like (not on cleanup). |

### Exceptions

| Exception | Thrown by | When |
| --- | --- | --- |
| `InvalidArgumentException` | `like()`, `unlike()`, `toggleLike()`, `likeBy()`, `unlikeBy()` | A model is unsaved or has no key, a key is longer than 64 characters, or a morph type is longer than 191. Nothing is written. |
| `Illuminate\Database\ClassMorphViolationException` | every method | `Relation::enforceMorphMap()` is on and a model has no alias. Nothing is written. |
| `Illuminate\Database\QueryException` | every method | Any database error other than the pair's own unique index refusing a duplicate. |

### Configuration and publish tags

| Key | Default |
| --- | --- |
| `pharaonic.likes.table` | `likes` |
| `pharaonic.likes.cleanup_on_delete` | `true` |

| Tag | Publishes |
| --- | --- |
| `likes-config`, `pharaonic-config` | `config/pharaonic/likes.php` |
| `likes-migrations`, `pharaonic-migrations` | `database/migrations/2026_11_04_000001_create_pharaonic_likes_table.php` |
| `laravel-likes`, `pharaonic` | both |
