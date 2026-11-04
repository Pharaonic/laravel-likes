<?php

use Pharaonic\Laravel\Likes\Models\Like;
use Pharaonic\Laravel\Likes\Tests\Fixtures\Post;
use Pharaonic\Laravel\Likes\Tests\Fixtures\UlidModel;
use Pharaonic\Laravel\Likes\Tests\Fixtures\User;
use Pharaonic\Laravel\Likes\Tests\Fixtures\UuidModel;

test('integer uuid and ulid keys share the table', function () {
    $user = User::create();
    $uuid = UuidModel::create();
    $ulid = UlidModel::create();
    $post = Post::create();

    $this->assertSame(36, strlen($uuid->getKey()));
    $this->assertSame(26, strlen($ulid->getKey()));

    $this->assertTrue($user->like($uuid));
    $this->assertTrue($uuid->like($post));
    $this->assertTrue($ulid->like($uuid));
    $this->assertTrue($uuid->like($ulid));
    $this->assertFalse($ulid->like($uuid));

    $this->assertTrue($uuid->likedBy($user));
    $this->assertTrue($post->likedBy($uuid));
    $this->assertTrue($ulid->hasLiked($uuid));
    $this->assertSame(4, Like::query()->count());

    $like = Like::whereLiker($ulid)->firstOrFail();
    $this->assertSame((string) $ulid->getKey(), $like->liker_id);
    $this->assertTrue($like->liker->is($ulid));
    $this->assertTrue($like->likeable->is($uuid));
});

test('integer keys are stored as strings', function () {
    $user = User::create();
    $post = Post::create();
    $user->like($post);

    $like = Like::query()->firstOrFail();

    $this->assertSame((string) $user->getKey(), $like->liker_id);
    $this->assertSame((string) $post->getKey(), $like->likeable_id);
});

test('queries work on string keyed parents', function () {
    $user = User::create();
    [$first, $second] = [UuidModel::create(), UuidModel::create()];
    $ulid = UlidModel::create();

    $user->like($first);
    $ulid->like($first);
    $ulid->like($second);

    $this->assertSame([$first->id], UuidModel::whereLikedBy($user)->pluck('id')->all());
    $this->assertSame(2, UuidModel::whereLikedBy($ulid)->count());

    $counts = UuidModel::withCount('likes')->get()->pluck('likes_count', 'id')->map(function ($value) {
        return (int) $value;
    });
    $this->assertSame(2, $counts[$first->id]);
    $this->assertSame(1, $counts[$second->id]);

    $models = UuidModel::with('likes.liker')->get()->keyBy('id');
    $this->assertCount(2, $models[$first->id]->likes);
    $this->assertCount(2, UlidModel::with('givenLikes.likeable')->firstOrFail()->givenLikes);
});
