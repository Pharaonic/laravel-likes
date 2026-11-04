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

    public function test_a_deadlock_outside_a_transaction_is_retried(): void
    {
        $attempts = $this->deadlockOnCreating(1);

        Event::fake([LikeCreated::class]);
        $user = User::create();
        $post = Post::create();

        $this->assertTrue($user->like($post));
        $this->assertSame(2, $attempts->count);
        $this->assertSame(1, Like::query()->count());
        Event::assertDispatchedTimes(LikeCreated::class, 1);
    }

    public function test_a_deadlock_that_keeps_happening_is_thrown(): void
    {
        $attempts = $this->deadlockOnCreating(PHP_INT_MAX);
        $user = User::create();
        $post = Post::create();

        try {
            $user->like($post);
            $this->fail('The deadlock was swallowed.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Deadlock found', $exception->getMessage());
        }

        $this->assertSame(3, $attempts->count);
        $this->assertSame(0, Like::query()->count());
    }

    public function test_a_deadlock_inside_a_transaction_is_left_to_the_caller(): void
    {
        $attempts = $this->deadlockOnCreating(1);
        $user = User::create();
        $post = Post::create();

        DB::beginTransaction();

        try {
            $user->like($post);
            $this->fail('The deadlock was retried inside the caller\'s transaction.');
        } catch (\PDOException $exception) {
            // Laravel 11+ rethrows it as DeadlockException, so the caller's DB::transaction() retries.
            $this->assertStringContainsString('Deadlock found', $exception->getMessage());
        } finally {
            DB::rollBack();
        }

        $this->assertSame(1, $attempts->count);
    }

    /**
     * Make the first $times inserts of a like fail as MySQL's deadlock victim.
     */
    private function deadlockOnCreating(int $times): \stdClass
    {
        $attempts = new \stdClass;
        $attempts->count = 0;

        Like::creating(function () use ($attempts, $times) {
            if (++$attempts->count > $times) {
                return;
            }

            $message = 'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction';
            $pdo = new \PDOException($message);
            $pdo->errorInfo = ['40001', 1213, 'Deadlock found when trying to get lock; try restarting transaction'];

            throw new QueryException('insert into `likes` ...', [], $pdo);
        });

        return $attempts;
    }
}
