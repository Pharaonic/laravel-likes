<?php

use Pharaonic\Laravel\Likes\Models\Like;
use Pharaonic\Laravel\Likes\Tests\Fixtures\Bot;
use Pharaonic\Laravel\Likes\Tests\Fixtures\Post;
use Pharaonic\Laravel\Likes\Tests\Fixtures\Product;
use Pharaonic\Laravel\Likes\Tests\Fixtures\SoftDeletableMember;
use Pharaonic\Laravel\Likes\Tests\Fixtures\SoftDeletablePost;
use Pharaonic\Laravel\Likes\Tests\Fixtures\User;

test('deleting a likeable deletes the likes it received', function () {
    $user = User::create();
    [$deleted, $kept] = [Post::create(), Post::create()];
    $user->like($deleted);
    Bot::create()->like($deleted);
    $user->like($kept);

    $deleted->delete();

    $this->assertSame(0, Like::whereLikeable($deleted)->count());
    $this->assertTrue($user->hasLiked($kept));
    $this->assertSame(1, Like::query()->count());
});

test('deleting a liker deletes the likes it gave', function () {
    $bot = Bot::create();
    $other = Bot::create();
    $post = Post::create();
    $bot->like($post);
    $bot->like(Product::create());
    $other->like($post);

    $bot->delete();

    $this->assertSame(0, Like::whereLiker($bot)->count());
    $this->assertTrue($post->likedBy($other));
});

test('deleting a model with both traits deletes both directions', function () {
    $alice = User::create();
    $bob = User::create();
    $post = Post::create();

    $alice->like($bob);
    $bob->like($alice);
    $alice->like($alice);
    $alice->like($post);
    $bob->like($post);

    $alice->delete();

    $this->assertSame(1, Like::query()->count());
    $this->assertTrue($post->likedBy($bob));
});

test('a soft deleted likeable keeps its likes until force deleted', function () {
    $user = User::create();
    $post = SoftDeletablePost::create();
    $user->like($post);

    $post->delete();
    $this->assertSame(1, Like::query()->count());

    $post->restore();
    $this->assertTrue($post->likedBy($user));

    $post->delete();
    $post->forceDelete();
    $this->assertSame(0, Like::query()->count());
});

test('force deleting a likeable that was never soft deleted deletes its likes', function () {
    $post = SoftDeletablePost::create();
    User::create()->like($post);

    $post->forceDelete();

    $this->assertSame(0, Like::query()->count());
});

test('a soft deleted liker keeps its likes until force deleted', function () {
    $member = SoftDeletableMember::create();
    $post = Post::create();
    $member->like($post);

    $member->delete();
    $this->assertSame(1, Like::query()->count());

    $member->restore();
    $this->assertTrue($member->hasLiked($post));

    $member->forceDelete();
    $this->assertSame(0, Like::query()->count());
});

test('cleanup can be turned off', function () {
    $this->app['config']->set('pharaonic.likes.cleanup_on_delete', false);

    $user = User::create();
    $post = Post::create();
    $user->like($post);
    $post->delete();
    $user->delete();

    $this->assertSame(1, Like::query()->count());
});

test('query builder deletes skip model events and cleanup', function () {
    $post = Post::create();
    User::create()->like($post);

    Post::query()->whereKey($post->getKey())->delete();

    $this->assertSame(1, Like::query()->count());

    // The documented manual cleanup.
    Like::query()->where('likeable_type', $post->getMorphClass())
        ->whereNotIn('likeable_id', Post::query()->pluck('id')->map(function ($value) {
            return (string) $value;
        }))
        ->delete();

    $this->assertSame(0, Like::query()->count());
});
