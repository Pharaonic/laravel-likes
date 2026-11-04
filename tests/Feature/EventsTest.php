<?php

namespace Pharaonic\Laravel\Likes\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Pharaonic\Laravel\Likes\Events\LikeCreated;
use Pharaonic\Laravel\Likes\Events\LikeRemoved;
use Pharaonic\Laravel\Likes\Models\Like;
use Pharaonic\Laravel\Likes\Tests\Fixtures\Post;
use Pharaonic\Laravel\Likes\Tests\Fixtures\User;
use Pharaonic\Laravel\Likes\Tests\TestCase;

class EventsTest extends TestCase
{
    public function test_like_created_is_dispatched_once_per_inserted_like(): void
    {
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
    }

    public function test_like_removed_is_dispatched_once_per_deleted_like(): void
    {
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
    }

    public function test_toggle_like_dispatches_the_event_of_the_change_it_made(): void
    {
        Event::fake([LikeCreated::class, LikeRemoved::class]);

        $user = User::create();
        $post = Post::create();

        $user->toggleLike($post);
        Event::assertDispatchedTimes(LikeCreated::class, 1);
        Event::assertNotDispatched(LikeRemoved::class);

        $user->toggleLike($post);
        Event::assertDispatchedTimes(LikeCreated::class, 1);
        Event::assertDispatchedTimes(LikeRemoved::class, 1);
    }

    public function test_listeners_run_after_the_write(): void
    {
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
    }

    public function test_cleanup_on_delete_dispatches_no_like_removed(): void
    {
        $post = Post::create();
        User::create()->like($post);

        Event::fake([LikeRemoved::class]);
        $post->delete();

        Event::assertNotDispatched(LikeRemoved::class);
        $this->assertSame(0, Like::query()->count());
    }
}
