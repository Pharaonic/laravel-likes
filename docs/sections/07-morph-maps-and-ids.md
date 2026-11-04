## Morph Maps and Ids

### Stored types

The `liker_type` and `likeable_type` columns store `$model->getMorphClass()`: the class name, or its alias when you register a morph map.

```php title="app/Providers/AppServiceProvider.php"
use Illuminate\Database\Eloquent\Relations\Relation;

public function boot()
{
    Relation::morphMap([
        'user' => \App\User::class,
        'post' => \App\Post::class,
    ]);
}
```

With this map a like stores `user` and `post`, and `$like->liker` resolves back to `App\User`. Register the map before you store likes: rows stored with class names are not rewritten when you add a map later, and lookups for `post` won't find rows stored as `App\Post`.

Types are limited to 191 characters. A longer class name or alias throws an `InvalidArgumentException` before anything is written; register a shorter alias.

### Integer, UUID and ULID keys

`liker_id` and `likeable_id` are `varchar(64)`, so models with different key types share the table:

```php
$user->like($document);     // integer liker, UUID likeable
$document->like($comment);  // UUID liker, integer likeable
```

Keys are stored as strings (`42` becomes `'42'`). A key longer than 64 characters throws an `InvalidArgumentException` instead of being cut.

### No foreign keys

The table has no `FOREIGN KEY` constraints. A polymorphic column points at a different table on each row (`users`, `bots`, `posts`), and a foreign key can only reference one table. The package deletes a model's likes when the model is deleted instead (see [Deletes and Soft Deletes](#deletion-and-soft-deletes)).
