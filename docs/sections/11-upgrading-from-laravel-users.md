## Upgrading from Laravel Users

`pharaonic/laravel-users` shipped likes as the `isLiker` and `isLikeable` traits. This package replaces them, but the move is a deliberate migration: the methods return different values, and the old table needs converting before this package can use it.

:::danger Back up first
Back up the `likes` table before you start, and run every step on a copy of production data first.
:::

### 1. Find the old traits

```bash title="Terminal" no-line-numbers
grep -rn "isLiker\|isLikeable\|Models\\\\Actions\\\\Like" app/
```

### 2. Replace the traits and calls

Replace `isLiker` with `CanLike` and `isLikeable` with `HasLikes`. Don't keep both systems on the same model: each deletes likes on delete.

| `laravel-users` | `laravel-likes` | What changed |
| --- | --- | --- |
| `$user->like($post)` | `$user->like($post)` | Returned true when already liked; now returns false when nothing changed. |
| `$user->unlike($post)` | `$user->unlike($post)` | Same meaning: true when removed. |
| `$user->liked($post)` | `$user->hasLiked($post)` | Renamed. |
| `$user->likes()` (likes given) | `$user->givenLikes()` | Renamed, so `likes()` always means likes received. |
| `$post->likeBy($user)` | `$post->likeBy($user)` | Returned true when already liked; now returns false when nothing changed. Accepts any model, not only `Authenticatable`. |
| `$post->unLikeBy($user)` | `$post->unlikeBy($user)` | Same meaning. `unLikeBy()` still works: PHP method names ignore case. |
| `$post->likedBy($user)` | `$post->likedBy($user)` | Same meaning. |
| `$post->likes()` | `$post->likes()` | Same meaning. |
| `Pharaonic\Laravel\Users\Models\Actions\Like` | `Pharaonic\Laravel\Likes\Models\Like` | New class. |

Check every caller that used the result of `like()` or `likeBy()` as "is liked now": use `hasLiked()` for that.

### 3. Check the stored types

The old traits searched by `get_class($model)`, while Eloquent stored `getMorphClass()`. The new package uses `getMorphClass()` for both. List what the table holds:

```sql
SELECT liker_type, likeable_type, COUNT(*) FROM likes GROUP BY liker_type, likeable_type;
```

- No morph map, and the table holds class names: nothing to change.
- A morph map, and the table holds aliases: nothing to change.
- A morph map, and the table holds class names (or a mix): decide which value to keep and update the rows deliberately, type by type, for example `UPDATE likes SET likeable_type = 'post' WHERE likeable_type = 'App\\Models\\Post';`. Don't rewrite types you haven't checked: two classes may share an alias, or an alias may have changed.

### 4. Remove duplicate rows

The old table had no unique index, so a pair can have several rows. Count them:

```sql
SELECT likeable_type, likeable_id, liker_type, liker_id, COUNT(*) AS copies
FROM likes
GROUP BY likeable_type, likeable_id, liker_type, liker_id
HAVING COUNT(*) > 1;
```

Keep the oldest row of each pair (the lowest id) and delete the others. The statement can run again safely: once no duplicates are left, it deletes nothing.

```sql
DELETE FROM likes
WHERE id NOT IN (
    SELECT keep_id FROM (
        SELECT MIN(id) AS keep_id
        FROM likes
        GROUP BY likeable_type, likeable_id, liker_type, liker_id
    ) AS kept
);
```

The extra derived table lets MySQL delete from the table it reads.

### 5. Check the column widths

The old columns are `varchar(255)`; the new ones are `varchar(191)` for types and `varchar(64)` for ids. MySQL can't index four `varchar(255)` utf8mb4 columns together, so shrink them, after checking no value is longer:

```sql
SELECT MAX(LENGTH(liker_type)), MAX(LENGTH(likeable_type)), MAX(LENGTH(liker_id)), MAX(LENGTH(likeable_id)) FROM likes;
```

Stop if a type exceeds 191 or an id exceeds 64 characters: register a shorter morph alias first (step 3).

### 6. Convert the table in a migration

The package migration creates the table and refuses to run over an existing one. Publish it and replace its body with the conversion, keeping the file name and class name, so Laravel records the conversion as the package migration:

```bash title="Terminal" no-line-numbers
php artisan vendor:publish --tag=likes-migrations
```

```php title="database/migrations/2026_11_04_000001_create_pharaonic_likes_table.php"
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePharaonicLikesTable extends Migration
{
    public function up()
    {
        // Converts the pharaonic/laravel-users table, after steps 3 to 5.
        Schema::table('likes', function (Blueprint $table) {
            $table->string('liker_type', 191)->change();
            $table->string('liker_id', 64)->change();
            $table->string('likeable_type', 191)->change();
            $table->string('likeable_id', 64)->change();

            $table->unique(['likeable_type', 'likeable_id', 'liker_type', 'liker_id'], 'likes_unique_pair');
            $table->index(['liker_type', 'liker_id'], 'likes_liker_index');
        });
    }

    public function down()
    {
        Schema::table('likes', function (Blueprint $table) {
            $table->dropUnique('likes_unique_pair');
            $table->dropIndex('likes_liker_index');
        });
    }
}
```

`change()` needs `doctrine/dbal` on Laravel 9 (`composer require doctrine/dbal`). The unique index fails to build while duplicates remain, which is your check that step 4 is complete.

### 7. Remove the old package

Remove `pharaonic/laravel-users` (or stop using its likes) before running `php artisan migrate`, so no model runs the old cleanup.

### 8. Verify before switching traffic

- Compare `SELECT COUNT(*) FROM likes` with the count before step 4, minus the duplicates you deleted.
- Check counts on a few pages (`withCount('likes')`), "liked by me" flags, and a like and unlike from a test account.
