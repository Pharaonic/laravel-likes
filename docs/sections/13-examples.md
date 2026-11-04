## Examples

### 1. A like button

Send the state the user wants, so a double click can't undo itself, and return the count for the button.

- ===Routes

  ```php title="routes/web.php"
  use App\Http\Controllers\PostLikeController;

  Route::middleware('auth')->group(function () {
      Route::post('posts/{post}/like', [PostLikeController::class, 'store']);
      Route::delete('posts/{post}/like', [PostLikeController::class, 'destroy']);
  });
  ```

- ===Controller

  ```php title="app/Http/Controllers/PostLikeController.php"
  namespace App\Http\Controllers;

  use App\Models\Post;
  use Illuminate\Http\Request;

  class PostLikeController extends Controller
  {
      public function store(Request $request, Post $post)
      {
          $request->user()->like($post);

          return ['liked' => true, 'likes' => $post->likes()->count()];
      }

      public function destroy(Request $request, Post $post)
      {
          $request->user()->unlike($post);

          return ['liked' => false, 'likes' => $post->likes()->count()];
      }
  }
  ```

### 2. A feed with counts and the viewer's likes

One query for the posts, their counts and whether the viewer liked each one.

```php title="app/Http/Controllers/FeedController.php"
public function index(Request $request)
{
    $posts = Post::withCount([
        'likes',
        'likes as liked_by_me' => fn ($query) => $query->whereLiker($request->user()),
    ])->latest()->paginate(20);

    return view('feed', compact('posts'));
}
```

### 3. A "liked posts" page

```php
$posts = Post::whereLikedBy($request->user())->latest()->paginate(20);
```

Or newest like first, with every type the user liked:

```php
$likes = $request->user()->givenLikes()->with('likeable')->latest()->paginate(20);
```

### 4. Users who like each other

`User` uses both traits, so users can like users.

```php title="app/Models/User.php"
class User extends Authenticatable
{
    use CanLike, HasLikes;
}
```

```php
$alice->like($bob);

$bob->likes()->with('liker')->get();      // who liked Bob
$alice->givenLikes()->count();            // how many likes Alice gave
User::whereLikedBy($alice)->get();        // the users Alice likes
```

### 5. Notifying the author

```php title="app/Listeners/NotifyAuthor.php"
public function handle(LikeCreated $event)
{
    if ($event->likeable instanceof Post && ! $event->likeable->author->is($event->liker)) {
        $event->likeable->author->notify(new PostLiked($event->liker));
    }
}
```

Laravel discovers it from the `LikeCreated` type of `handle()`; see [Events](#events) to register it yourself.
