## Events

The package dispatches two events, only when a call actually changed the table:

| Event | Dispatched by | Not dispatched |
| --- | --- | --- |
| `Pharaonic\Laravel\Likes\Events\LikeCreated` | `like()`, `likeBy()`, `toggleLike()` when they insert the like | When the like already existed |
| `Pharaonic\Laravel\Likes\Events\LikeRemoved` | `unlike()`, `unlikeBy()`, `toggleLike()` when they delete the like | When there was no like, and for the cleanup on delete |

Both have three public properties: `$like` (the `Like` row), `$liker`, and `$likeable`. In `LikeRemoved`, `$like` is the deleted row, with `exists` false.

```php title="app/Listeners/NotifyAuthor.php"
namespace App\Listeners;

use App\Notifications\PostLiked;
use Pharaonic\Laravel\Likes\Events\LikeCreated;

class NotifyAuthor
{
    public function handle(LikeCreated $event)
    {
        if ($event->likeable instanceof \App\Models\Post) {
            $event->likeable->author->notify(new PostLiked($event->liker));
        }
    }
}
```

```php title="app/Providers/EventServiceProvider.php"
protected $listen = [
    \Pharaonic\Laravel\Likes\Events\LikeCreated::class => [
        \App\Listeners\NotifyAuthor::class,
    ],
];
```

### When listeners run

The events are dispatched right after the insert or delete. When you like inside a database transaction, the listeners run before that transaction commits, and a rollback afterwards does not take the event back. Make a listener that must only see committed likes a queued listener dispatched after your transaction, or call `like()` outside it.

### Model events

`LikeCreated` comes with the `Like` model's own `creating` and `created` events, because the like is saved through Eloquent; a `creating` listener that returns false cancels the like, and `like()` then returns false. The removal deletes the row with a query, so the `Like` model's `deleting` and `deleted` events do not fire. Listen to `LikeRemoved` instead.
