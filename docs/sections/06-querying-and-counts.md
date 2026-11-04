## Querying and Counts

Counting and filtering run in SQL. None of them loads the likes into PHP.

### Counting

```php
$post->likes()->count();               // select count(*) ...

$posts = Post::withCount('likes')->get();
$posts->first()->likes_count;          // 12

$post->loadCount('likes');
```

:::warning
`$post->likes->count()` (property, not method) loads every like of the post first. Use `likes()->count()` or `withCount('likes')`. The package has no `likes_count` accessor, so it never runs a hidden query.
:::

### Filtering by liker

`whereLikedBy()` keeps the models the liker likes, through `whereHas()`:

```php
Post::whereLikedBy($user)->latest()->paginate();
```

### "Did I like this?" in a list

Calling `$user->hasLiked($post)` for every post of a page runs one query per post. Count the viewer's like in the same query instead:

```php
$posts = Post::withCount([
    'likes',
    'likes as liked_by_me' => fn ($query) => $query->whereLiker($request->user()),
])->latest()->paginate();

$post->likes_count;        // 12
(bool) $post->liked_by_me; // true when the viewer liked it
```

### Eager loading

Load likes and both ends with a fixed number of queries:

```php
Post::with('likes.liker')->get();                 // posts, likes, then one query per liker type
$user->givenLikes()->with('likeable')->latest()->get();
```

### Indexes

The migration creates:

| Index | Columns | Serves |
| --- | --- | --- |
| `likes_unique_pair` (unique) | `likeable_type`, `likeable_id`, `liker_type`, `liker_id` | Uniqueness, `likes()`, `withCount('likes')`, `likedBy()` |
| `likes_liker_index` | `liker_type`, `liker_id` | `givenLikes()`, `whereLikedBy()` |

The ids are strings, so the relations compare your model's key as a string too. That keeps both indexes usable, and lets PostgreSQL compare a `bigint` or `uuid` key with the `varchar` column at all.
