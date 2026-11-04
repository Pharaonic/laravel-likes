<?php

namespace Pharaonic\Laravel\Likes\Tests\Feature;

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
use Pharaonic\Laravel\Likes\Tests\TestCase;

class IntegrityTest extends TestCase
{
    public function test_a_like_written_by_another_connection_is_reported_as_already_liked(): void
    {
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
    }

    public function test_a_violation_of_another_unique_index_is_not_swallowed(): void
    {
        Schema::table('likes', function (Blueprint $table) {
            $table->unique(['liker_type', 'liker_id'], 'likes_one_per_liker');
        });

        $user = User::create();
        $this->assertTrue($user->like(Post::create()));

        $this->expectException(QueryException::class);

        $user->like(Post::create());
    }

    public function test_other_database_errors_are_not_swallowed(): void
    {
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
    }

    public function test_an_already_liked_pair_leaves_the_callers_transaction_usable(): void
    {
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
    }

    public function test_a_rolled_back_transaction_rolls_the_like_back(): void
    {
        $user = User::create();
        $post = Post::create();

        DB::beginTransaction();
        $this->assertTrue($user->like($post));
        DB::rollBack();

        $this->assertFalse($user->hasLiked($post));
    }

    public function test_a_model_event_can_cancel_the_insert(): void
    {
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
    }
}
