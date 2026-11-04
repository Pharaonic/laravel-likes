## Deletes and Soft Deletes

When a model that uses `CanLike` or `HasLikes` is deleted through Eloquent, its likes are deleted with it:

| Action | Likes it gave (`CanLike`) | Likes it received (`HasLikes`) |
| --- | --- | --- |
| `$model->delete()` | deleted | deleted |
| `$model->delete()` on a `SoftDeletes` model | kept | kept |
| `$model->restore()` | still there | still there |
| `$model->forceDelete()` | deleted | deleted |

A model with both traits loses both directions. Keeping the likes on a soft delete means a restored model has exactly the likes it had.

```php
$post->delete();       // SoftDeletes: the likes stay
$post->restore();      // $post->likedBy($user) is still true
$post->forceDelete();  // the likes are deleted
```

The cleanup deletes the rows with one query and dispatches no `LikeRemoved` events.

### Turning cleanup off

```php title="config/pharaonic/likes.php"
'cleanup_on_delete' => false,
```

Deleting a model then leaves its likes in the table. `whereLikedBy()` and `withCount()` ignore them for models that no longer exist, but `givenLikes()` of a liker still lists likes of deleted posts.

### Deletes that skip the cleanup

The cleanup runs in the model's `deleted` event, so it only happens when a model instance is deleted. These skip it:

- `Post::where(...)->delete()` and other query deletes;
- `DB::table('posts')->delete()` and raw SQL;
- `truncate()`.

After such a delete, remove the likes yourself, for example in the same job:

```php
use Pharaonic\Laravel\Likes\Models\Like;

$ids = Post::where('created_at', '<', now()->subYear())->pluck('id');

Post::whereIn('id', $ids)->delete();
Like::where('likeable_type', (new Post)->getMorphClass())
    ->whereIn('likeable_id', $ids->map(fn ($id) => (string) $id))
    ->delete();
```

To clean up after the fact, delete the likes whose model is gone:

```php
Like::where('likeable_type', (new Post)->getMorphClass())
    ->whereNotIn('likeable_id', Post::withTrashed()->pluck('id')->map(fn ($id) => (string) $id))
    ->delete();
```

Leave out `withTrashed()` for models without `SoftDeletes`. Run it per type, and for `liker_type` the same way.
