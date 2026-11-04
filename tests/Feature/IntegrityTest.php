<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Pharaonic\Laravel\Likes\Events\LikeCreated;
use Pharaonic\Laravel\Likes\Models\Like;
use Pharaonic\Laravel\Likes\Tests\Fixtures\Post;
use Pharaonic\Laravel\Likes\Tests\Fixtures\User;

test('a like written by another connection is reported as already liked', function () {
    $this->useSharedDatabase();
    $user = User::create();
    $post = Post::create();

    // A second, independent connection to the same database, as another request would have.
    $this->app['config']->set('database.connections.other', $this->app['config']->get('database.connections.testing'));
    DB::connection('other')->table('likes')->insert([
        'liker_type' => User::class,
        'liker_id' => (string) $user->getKey(),
        'likeable_type' => Post::class,
        'likeable_id' => (string) $post->getKey(),
        'created_at' => Carbon::now(),
        'updated_at' => Carbon::now(),
    ]);
    DB::disconnect('other');

    Event::fake([LikeCreated::class]);

    // like() does not read first: the insert hits the unique index.
    $this->assertFalse($user->like($post));
    $this->assertSame(1, Like::query()->count());
    Event::assertNotDispatched(LikeCreated::class);
});

test('a violation of another unique index is not swallowed', function () {
    Schema::table('likes', function (Blueprint $table) {
        $table->unique(['liker_type', 'liker_id'], 'likes_one_per_liker');
    });

    $user = User::create();
    $this->assertTrue($user->like(Post::create()));

    $this->expectException(QueryException::class);

    $user->like(Post::create());
});

test('other database errors are not swallowed', function () {
    $user = User::create();
    $post = Post::create();
    Schema::drop('likes');

    foreach (['like', 'unlike', 'hasLiked', 'toggleLike'] as $method) {
        try {
            $user->{$method}($post);
            $this->fail("{$method}() hid a missing table.");
        } catch (QueryException $exception) {
            $this->assertStringContainsString('likes', $exception->getMessage());
        }
    }
});

test('an already liked pair leaves the callers transaction usable', function () {
    $user = User::create();
    $post = Post::create();
    $user->like($post);

    DB::transaction(function () use ($user, $post) {
        $this->assertFalse($user->like($post));
        $this->assertFalse($user->toggleLike($post));
        $this->assertTrue($user->like($post));
        $this->assertSame(1, Post::query()->count());
    });

    $this->assertTrue($user->hasLiked($post));
});

test('a rolled back transaction rolls the like back', function () {
    $user = User::create();
    $post = Post::create();

    DB::beginTransaction();
    $this->assertTrue($user->like($post));
    DB::rollBack();

    $this->assertFalse($user->hasLiked($post));
});

test('a model event can cancel the insert', function () {
    Like::creating(function (Model $like) {
        return false;
    });

    Event::fake([LikeCreated::class]);
    $user = User::create();
    $post = Post::create();

    $this->assertFalse($user->like($post));
    $this->assertFalse($user->toggleLike($post));
    $this->assertSame(0, Like::query()->count());
    Event::assertNotDispatched(LikeCreated::class);
});
