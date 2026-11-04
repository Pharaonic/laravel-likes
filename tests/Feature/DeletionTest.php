<?php

namespace Pharaonic\Laravel\Likes\Tests\Feature;

use Pharaonic\Laravel\Likes\Models\Like;
use Pharaonic\Laravel\Likes\Tests\Fixtures\Bot;
use Pharaonic\Laravel\Likes\Tests\Fixtures\Post;
use Pharaonic\Laravel\Likes\Tests\Fixtures\Product;
use Pharaonic\Laravel\Likes\Tests\Fixtures\SoftDeletableMember;
use Pharaonic\Laravel\Likes\Tests\Fixtures\SoftDeletablePost;
use Pharaonic\Laravel\Likes\Tests\Fixtures\User;
use Pharaonic\Laravel\Likes\Tests\TestCase;

class DeletionTest extends TestCase
{
    public function test_deleting_a_likeable_deletes_the_likes_it_received(): void
    {
        $user = User::create();
        [$deleted, $kept] = [Post::create(), Post::create()];
        $user->like($deleted);
        Bot::create()->like($deleted);
        $user->like($kept);

        $deleted->delete();

        $this->assertSame(0, Like::whereLikeable($deleted)->count());
        $this->assertTrue($user->hasLiked($kept));
        $this->assertSame(1, Like::query()->count());
    }

    public function test_deleting_a_liker_deletes_the_likes_it_gave(): void
    {
        $bot = Bot::create();
        $other = Bot::create();
        $post = Post::create();
        $bot->like($post);
        $bot->like(Product::create());
        $other->like($post);

        $bot->delete();

        $this->assertSame(0, Like::whereLiker($bot)->count());
        $this->assertTrue($post->likedBy($other));
    }

    public function test_deleting_a_model_with_both_traits_deletes_both_directions(): void
    {
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
    }

    public function test_a_soft_deleted_likeable_keeps_its_likes_until_force_deleted(): void
    {
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
    }

    public function test_force_deleting_a_likeable_that_was_never_soft_deleted_deletes_its_likes(): void
    {
        $post = SoftDeletablePost::create();
        User::create()->like($post);

        $post->forceDelete();

        $this->assertSame(0, Like::query()->count());
    }

    public function test_a_soft_deleted_liker_keeps_its_likes_until_force_deleted(): void
    {
        $member = SoftDeletableMember::create();
        $post = Post::create();
        $member->like($post);

        $member->delete();
        $this->assertSame(1, Like::query()->count());

        $member->restore();
        $this->assertTrue($member->hasLiked($post));

        $member->forceDelete();
        $this->assertSame(0, Like::query()->count());
    }

    public function test_cleanup_can_be_turned_off(): void
    {
        $this->app['config']->set('pharaonic.likes.cleanup_on_delete', false);

        $user = User::create();
        $post = Post::create();
        $user->like($post);
        $post->delete();
        $user->delete();

        $this->assertSame(1, Like::query()->count());
    }

    public function test_query_builder_deletes_skip_model_events_and_cleanup(): void
    {
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
    }
}
