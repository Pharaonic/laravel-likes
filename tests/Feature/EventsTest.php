<?php

use Illuminate\Support\Facades\Event;
use Pharaonic\Laravel\Likes\Events\LikeCreated;
use Pharaonic\Laravel\Likes\Events\LikeRemoved;
use Pharaonic\Laravel\Likes\Models\Like;
use Pharaonic\Laravel\Likes\Tests\Fixtures\Post;
use Pharaonic\Laravel\Likes\Tests\Fixtures\User;

test('like created is dispatched once per inserted like', function () {
    Event::fake([LikeCreated::class, LikeRemoved::class]);

    $user = User::create();
    $post = Post::create();
    $user->like($post);
    $user->like($post);
    $post->likeBy($user);

    Event::assertDispatchedTimes(LikeCreated::class, 1);
    Event::assertDispatched(LikeCreated::class, function (LikeCreated $event) use ($user, $post) {
        return $event->liker->is($user)
            && $event->likeable->is($post)
            && $event->like->exists
            && $event->like->is(Like::query()->firstOrFail());
    });
    Event::assertNotDispatched(LikeRemoved::class);
});

test('like removed is dispatched once per deleted like', function () {
    $user = User::create();
    $post = Post::create();
    $user->like($post);
    $id = Like::query()->firstOrFail()->getKey();

    Event::fake([LikeCreated::class, LikeRemoved::class]);

    $user->unlike($post);
    $user->unlike($post);
    $post->unlikeBy($user);

    Event::assertDispatchedTimes(LikeRemoved::class, 1);
    Event::assertDispatched(LikeRemoved::class, function (LikeRemoved $event) use ($user, $post, $id) {
        return $event->liker->is($user)
            && $event->likeable->is($post)
            && $event->like->getKey() === $id
            && ! $event->like->exists;
    });
    Event::assertNotDispatched(LikeCreated::class);
});

test('toggle like dispatches the event of the change it made', function () {
    Event::fake([LikeCreated::class, LikeRemoved::class]);

    $user = User::create();
    $post = Post::create();

    $user->toggleLike($post);
    Event::assertDispatchedTimes(LikeCreated::class, 1);
    Event::assertNotDispatched(LikeRemoved::class);

    $user->toggleLike($post);
    Event::assertDispatchedTimes(LikeCreated::class, 1);
    Event::assertDispatchedTimes(LikeRemoved::class, 1);
});

test('listeners run after the write', function () {
    $counts = [];

    Event::listen(LikeCreated::class, function () use (&$counts) {
        $counts[] = Like::query()->count();
    });
    Event::listen(LikeRemoved::class, function () use (&$counts) {
        $counts[] = Like::query()->count();
    });

    $user = User::create();
    $post = Post::create();
    $user->like($post);
    $user->unlike($post);

    $this->assertSame([1, 0], $counts);
});

test('cleanup on delete dispatches no like removed', function () {
    $post = Post::create();
    User::create()->like($post);

    Event::fake([LikeRemoved::class]);
    $post->delete();

    Event::assertNotDispatched(LikeRemoved::class);
    $this->assertSame(0, Like::query()->count());
});
