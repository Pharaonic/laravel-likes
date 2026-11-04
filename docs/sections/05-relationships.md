## Relationships

All relations are native Eloquent relations, so everything Eloquent offers works on them.

| Relation | On | Type | Holds |
| --- | --- | --- | --- |
| `givenLikes()` | `CanLike` models | `MorphMany` of `Like` | The likes the model gave. |
| `likes()` | `HasLikes` models | `MorphMany` of `Like` | The likes the model received. |
| `liker()` | `Like` | `MorphTo` | The model that gave the like. |
| `likeable()` | `Like` | `MorphTo` | The model that received the like. |

### Why `givenLikes()` and not `likes()`

The liker side's relation is called `givenLikes()` so it never clashes with `likes()` on the liked side. A `User` can use both traits without a trait conflict, and `$user->likes` always means the likes the user received.

### Reading through the relations

```php
use Pharaonic\Laravel\Likes\Models\Like;

$like = $post->likes()->latest()->first();

$like->liker;    // App\Models\User
$like->likeable; // App\Models\Post
$like->created_at;

$user->givenLikes()->with('likeable')->latest()->get()
    ->map(fn (Like $like) => $like->likeable); // the posts, comments, ... the user liked
```

### The `Like` model

`Pharaonic\Laravel\Likes\Models\Like` reads the table from `pharaonic.likes.table` and has standard timestamps. It has two scopes for filtering likes by one end:

```php
Like::whereLiker($user)->count();      // likes the user gave
Like::whereLikeable($post)->count();   // likes the post received

User::whereHas('givenLikes', fn ($query) => $query->whereLikeable($post))->get(); // who liked the post
```

The likes table lives on the default database connection. Counts, filters and eager loading need it in the same database as your models.
