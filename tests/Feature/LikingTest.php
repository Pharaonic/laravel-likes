<?php

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Pharaonic\Laravel\Likes\Models\Like;
use Pharaonic\Laravel\Likes\Tests\Fixtures\Bot;
use Pharaonic\Laravel\Likes\Tests\Fixtures\Comment;
use Pharaonic\Laravel\Likes\Tests\Fixtures\Post;
use Pharaonic\Laravel\Likes\Tests\Fixtures\Product;
use Pharaonic\Laravel\Likes\Tests\Fixtures\User;
use Pharaonic\Laravel\Likes\Tests\Fixtures\UuidModel;

test('like returns true only when it creates the like', function () {
    $user = User::create();
    $post = Post::create();

    $this->assertTrue($user->like($post));
    $this->assertFalse($user->like($post));
    $this->assertFalse($user->like($post));
    $this->assertSame(1, Like::query()->count());
});

test('unlike returns true only when it removes the like', function () {
    $user = User::create();
    $post = Post::create();

    $this->assertFalse($user->unlike($post));

    $user->like($post);

    $this->assertTrue($user->unlike($post));
    $this->assertFalse($user->unlike($post));
    $this->assertSame(0, Like::query()->count());
});

test('has liked and liked by report the current state', function () {
    $user = User::create();
    $post = Post::create();

    $this->assertFalse($user->hasLiked($post));
    $this->assertFalse($post->likedBy($user));

    $user->like($post);

    $this->assertTrue($user->hasLiked($post));
    $this->assertTrue($post->likedBy($user));
});

test('like by and unlike by return whether they changed anything', function () {
    $user = User::create();
    $post = Post::create();

    $this->assertTrue($post->likeBy($user));
    $this->assertFalse($post->likeBy($user));
    $this->assertTrue($post->unlikeBy($user));
    $this->assertFalse($post->unlikeBy($user));
});

test('both sides work on the same row', function () {
    $user = User::create();
    $post = Post::create();

    $user->like($post);
    $this->assertTrue($post->likedBy($user));
    $this->assertFalse($post->likeBy($user));

    $this->assertTrue($post->unlikeBy($user));
    $this->assertFalse($user->hasLiked($post));

    $this->assertTrue($post->likeBy($user));
    $this->assertTrue($user->hasLiked($post));
    $this->assertTrue($user->unlike($post));
    $this->assertSame(0, Like::query()->count());
});

test('the legacy un like by spelling calls unlike by', function () {
    $user = User::create();
    $post = Post::create();
    $post->likeBy($user);

    // PHP method names are case-insensitive.
    $this->assertTrue($post->unLikeBy($user));
});

test('toggle like returns the new state', function () {
    $user = User::create();
    $post = Post::create();

    $this->assertTrue($user->toggleLike($post));
    $this->assertSame(1, Like::query()->count());

    $this->assertFalse($user->toggleLike($post));
    $this->assertSame(0, Like::query()->count());

    $this->assertTrue($user->toggleLike($post));
    $this->assertTrue($user->hasLiked($post));
    $this->assertSame(1, Like::query()->count());
});

test('a liker can like any model', function () {
    $user = User::create();
    $other = User::create();

    $this->assertTrue($user->like(Post::create()));
    $this->assertTrue($user->like(Comment::create()));
    $this->assertTrue($user->like(Product::create()));
    $this->assertTrue($user->like($other));
    $this->assertTrue($user->like($user));

    $this->assertSame(5, $user->givenLikes()->count());
    $this->assertTrue($other->likedBy($user));
    $this->assertTrue($user->likedBy($user));
});

test('a post and a comment with the same id are different targets', function () {
    $user = User::create();
    $post = Post::create();
    $comment = Comment::create();
    $this->assertSame($post->getKey(), $comment->getKey());

    $user->like($post);

    $this->assertTrue($user->hasLiked($post));
    $this->assertFalse($user->hasLiked($comment));
    $this->assertTrue($user->like($comment));
});

test('many likers can like many targets', function () {
    $alice = User::create();
    $bob = User::create();
    $first = Post::create();
    $second = Post::create();

    $this->assertTrue($alice->like($first));
    $this->assertTrue($bob->like($first));
    $this->assertTrue($alice->like($second));

    $this->assertSame(2, $first->likes()->count());
    $this->assertSame(1, $second->likes()->count());
    $this->assertSame(2, $alice->givenLikes()->count());
    $this->assertSame(1, $bob->givenLikes()->count());
});

test('the liker does not have to be a user or signed in', function () {
    $bot = Bot::create();
    $post = Post::create();

    $this->assertFalse(Auth::check());
    $this->assertTrue($bot->like($post));
    $this->assertTrue($post->likedBy($bot));
    $this->assertInstanceOf(Bot::class, $post->likes()->firstOrFail()->liker);
});

test('writes reject an unsaved liker without creating a row', function () {
    $post = Post::create();

    foreach (['like', 'unlike', 'toggleLike'] as $method) {
        try {
            (new User)->{$method}($post);
            $this->fail("{$method}() accepted an unsaved liker.");
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('The liker ['.User::class.'] is not saved', $exception->getMessage());
        }
    }

    $this->expectException(InvalidArgumentException::class);

    try {
        $post->likeBy(new User);
    } finally {
        $this->assertSame(0, Like::query()->count());
    }
});

test('writes reject an unsaved likeable without creating a row', function () {
    $user = User::create();

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('The likeable ['.Post::class.'] is not saved');

    try {
        $user->like(new Post);
    } finally {
        $this->assertSame(0, Like::query()->count());
    }
});

test('reads treat an unsaved model as not liked', function () {
    $user = User::create();
    $post = Post::create();
    $user->like($post);

    $this->assertFalse($user->hasLiked(new Post));
    $this->assertFalse($post->likedBy(new User));
    $this->assertSame(0, Post::whereLikedBy(new User)->count());
});

test('a key longer than the column is rejected', function () {
    $target = new UuidModel;
    $target->exists = true;
    $target->id = str_repeat('a', 65);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('is 65 characters long; the likes table stores keys of up to 64 characters');

    try {
        User::create()->like($target);
    } finally {
        $this->assertSame(0, Like::query()->count());
    }
});

test('a morph type longer than the column is rejected', function () {
    Relation::morphMap([str_repeat('p', 192) => Post::class]);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Register a shorter alias with Relation::morphMap()');

    User::create()->like(Post::create());
});
