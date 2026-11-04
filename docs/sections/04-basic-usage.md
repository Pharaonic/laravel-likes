## Basic Usage

Add `CanLike` to the model that likes and `HasLikes` to the model that is liked.

```php title="app/Models/User.php"
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Pharaonic\Laravel\Likes\Concerns\CanLike;

class User extends Authenticatable
{
    use CanLike;
}
```

```php title="app/Models/Post.php"
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Likes\Concerns\HasLikes;

class Post extends Model
{
    use HasLikes;
}
```

The liker doesn't have to be a user, and nothing in the package reads the authenticated user: a `Team`, a `Bot` or any other model can use `CanLike`. A model can be liked without `HasLikes` too; the trait adds the `likes()` relation and the `likeBy()`, `unlikeBy()`, `likedBy()` and `whereLikedBy()` helpers.

### Liking and unliking

`like()` and `unlike()` return whether **this call** changed the table, not the current state:

```php
$user->like($post);     // true: the like was created
$user->like($post);     // false: it already existed, nothing changed

$user->unlike($post);   // true: the like was removed
$user->unlike($post);   // false: there was no like, nothing changed

$user->hasLiked($post); // false
```

Both are idempotent: calling them again leaves the same state. Use `hasLiked()` when you need the current state.

### Toggling

`toggleLike()` returns the **new state**, true when the pair is liked after the call:

```php
$user->toggleLike($post); // true: now liked
$user->toggleLike($post); // false: now not liked
```

A toggle is not idempotent: two calls cancel each other. When two toggles of the same pair arrive at the same moment, the result is not guaranteed to be what two toggles in a row would give (see [Integrity and Concurrency](#integrity-and-concurrency)). Prefer `like()` and `unlike()` for endpoints that a user can click twice.

### From the liked model

`HasLikes` adds the same operations seen from the other side. They work on the same row as the `CanLike` methods:

```php
$post->likeBy($user);    // true when created, like $user->like($post)
$post->likedBy($user);   // true, like $user->hasLiked($post)
$post->unlikeBy($user);  // true when removed, like $user->unlike($post)

$user->like($post);
$post->unlikeBy($user);  // true
$user->hasLiked($post);  // false
```

### A model that likes and is liked

A model can use both traits. `givenLikes()` holds the likes it gave and `likes()` the likes it received:

```php
class User extends Authenticatable
{
    use CanLike, HasLikes;
}

$alice->like($bob);

$alice->givenLikes()->count(); // 1
$alice->likes()->count();      // 0
$bob->likes()->count();        // 1
$bob->likedBy($alice);         // true
```

### Unsaved models

The writes need both models saved, because a like stores their keys. `like()`, `unlike()`, `toggleLike()`, `likeBy()` and `unlikeBy()` throw an `InvalidArgumentException` before touching the database when either model is unsaved. The reads `hasLiked()` and `likedBy()` return false, and `whereLikedBy()` matches nothing.

```php
$user->like(new Post); // InvalidArgumentException: The likeable [App\Models\Post] is not saved: ...
$user->hasLiked(new Post); // false
```
