<?php

namespace Pharaonic\Laravel\Likes\Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Pharaonic\Laravel\Likes\Models\Like;
use Pharaonic\Laravel\Likes\Tests\Fixtures\Post;
use Pharaonic\Laravel\Likes\Tests\Fixtures\User;
use Pharaonic\Laravel\Likes\Tests\TestCase;
use RuntimeException;

class MigrationTest extends TestCase
{
    private const MIGRATION = '2026_11_04_000001_create_pharaonic_likes_table';

    public function test_migrate_creates_the_likes_table(): void
    {
        $this->assertTrue(Schema::hasTable('likes'));
        $this->assertTrue(Schema::hasColumns('likes', [
            'id', 'liker_type', 'liker_id', 'likeable_type', 'likeable_id', 'created_at', 'updated_at',
        ]));
        $this->assertSame(1, DB::table('migrations')->where('migration', self::MIGRATION)->count());
    }

    public function test_the_unique_index_refuses_a_second_row_for_a_pair(): void
    {
        $row = ['liker_type' => 'user', 'liker_id' => '1', 'likeable_type' => 'post', 'likeable_id' => '1'];
        DB::table('likes')->insert($row);

        $this->expectException(QueryException::class);

        DB::table('likes')->insert($row);
    }

    public function test_the_unique_index_allows_the_same_ids_with_other_types(): void
    {
        DB::table('likes')->insert(['liker_type' => 'user', 'liker_id' => '1', 'likeable_type' => 'post', 'likeable_id' => '1']);
        DB::table('likes')->insert(['liker_type' => 'user', 'liker_id' => '1', 'likeable_type' => 'comment', 'likeable_id' => '1']);
        DB::table('likes')->insert(['liker_type' => 'bot', 'liker_id' => '1', 'likeable_type' => 'post', 'likeable_id' => '1']);

        $this->assertSame(3, DB::table('likes')->count());
    }

    public function test_rollback_drops_the_table_and_migrate_creates_it_again(): void
    {
        $this->artisanCall('migrate:rollback');
        $this->assertFalse(Schema::hasTable('likes'));

        $this->artisanCall('migrate');
        $this->assertTrue(Schema::hasTable('likes'));
        $this->assertTrue(User::create()->like(Post::create()));
    }

    public function test_the_configured_table_name_is_used_by_the_migration_and_the_model(): void
    {
        $this->artisanCall('migrate:rollback');
        $this->app['config']->set('pharaonic.likes.table', 'post_likes');
        $this->artisanCall('migrate');

        $this->assertTrue(Schema::hasTable('post_likes'));
        $this->assertFalse(Schema::hasTable('likes'));
        $this->assertSame('post_likes', (new Like)->getTable());

        $user = User::create();
        $post = Post::create();
        $this->assertTrue($user->like($post));
        $this->assertFalse($user->like($post));
        $this->assertSame(1, DB::table('post_likes')->count());
        $this->assertSame(1, Post::whereLikedBy($user)->count());
        $this->assertSame(1, (int) Post::withCount('likes')->firstOrFail()->getAttribute('likes_count'));
    }

    public function test_an_existing_likes_table_stops_the_migration_with_an_explanation(): void
    {
        $this->artisanCall('migrate:rollback');

        // The pharaonic/laravel-users table: no unique index, 255 character columns.
        Schema::create('likes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('likeable_id');
            $table->string('likeable_type');
            $table->string('liker_id');
            $table->string('liker_type');
            $table->timestamps();
        });

        try {
            $this->artisanCall('migrate');
            $this->fail('The migration ran over an existing table.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('The [likes] table already exists', $exception->getMessage());
            $this->assertStringContainsString('upgrade guide', $exception->getMessage());
        }

        $this->assertSame(0, DB::table('migrations')->where('migration', self::MIGRATION)->count());
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function artisanCall(string $command, array $parameters = []): void
    {
        $this->assertSame(0, $this->app->make(Kernel::class)->call($command, $parameters));
    }
}
