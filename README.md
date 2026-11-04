<p align="center"><a href="https://pharaonic.dev/packages/laravel/likes" target="_blank"><img src="https://raw.githubusercontent.com/Pharaonic/laravel-likes/develop/docs/cover.webp"></a></p>

<p align="center">
  <a href="https://php.net" target="_blank"><img src="https://img.shields.io/static/v1?label=PHP&message=8.0%20|%208.1&color=blue&style=flat-square" alt="PHP Version : 8.0 | 8.1"></a>
  <a href="https://laravel.com" target="_blank"><img src="https://img.shields.io/static/v1?label=Laravel&message=8.x&color=F05340&style=flat-square" alt="Laravel Version : 8.x"></a>
  <img src="https://img.shields.io/static/v1?label=License&message=MIT&color=brightgreen&style=flat-square" alt="License">
  <img src="https://github.com/Pharaonic/laravel-likes/actions/workflows/build.yml/badge.svg?branch=8.x" alt="Build">
  <br>
  <a href="https://packagist.org/packages/Pharaonic/laravel-likes" target="_blank"><img src="https://img.shields.io/static/v1?label=Packagist&message=pharaonic/laravel-likes&color=blue&logo=packagist&logoColor=white" alt="Source"></a>
  <a href="https://packagist.org/packages/pharaonic/laravel-likes" target="_blank"><img src="https://poser.pugx.org/pharaonic/laravel-likes/v" alt="Packagist Version"></a>
  <a href="https://packagist.org/packages/pharaonic/laravel-likes" target="_blank"><img src="https://poser.pugx.org/pharaonic/laravel-likes/downloads" alt="Packagist Downloads"></a>
</p>

<h3 align="center">Polymorphic likes for Eloquent models, kept unique by the database.</h3>
<br>

## Installation

```bash
composer require pharaonic/laravel-likes
php artisan migrate
```

The service provider is registered automatically, and `migrate` creates the `likes` table. Publish the migration only to change it (`--tag=likes-migrations`), and the configuration only to rename the table or turn off cleanup on delete:

```bash
php artisan vendor:publish --tag=likes-config
```

## Usage

Add `CanLike` to the model that likes (it does not have to be a user) and `HasLikes` to the model that is liked. A model can use both.

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Pharaonic\Laravel\Likes\Concerns\CanLike;

class User extends Authenticatable
{
    use CanLike;
}
```

```php
use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Likes\Concerns\HasLikes;

class Post extends Model
{
    use HasLikes;
}
```

```php
$user->like($post);       // true: this call created the like
$user->like($post);       // false: it already existed
$user->hasLiked($post);   // true
$user->unlike($post);     // true: this call removed it, false when there was none
$user->toggleLike($post); // the new state: true when liked

$post->likeBy($user);     // same as $user->like($post)
$post->unlikeBy($user);   // same as $user->unlike($post)
$post->likedBy($user);    // same as $user->hasLiked($post)

Post::withCount('likes')->with('likes.liker')->get();
Post::whereLikedBy($user)->latest()->paginate();
$user->givenLikes()->with('likeable')->latest()->get();
```

## Documentation

Full documentation: [Laravel Likes Documentation](https://pharaonic.dev/packages/laravel/likes).

## Support & Security

See [SUPPORT.md](SUPPORT.md) and [SECURITY.md](SECURITY.md). Contributions are welcome; see [CONTRIBUTING.md](CONTRIBUTING.md).

## License

MIT. See [LICENSE](LICENSE).
