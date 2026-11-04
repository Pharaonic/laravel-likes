<?php

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Pharaonic\Laravel\Likes\Models\Like;
use Pharaonic\Laravel\Likes\Tests\Fixtures\Post;
use Pharaonic\Laravel\Likes\Tests\Fixtures\User;

test('migrate creates the likes table', function () {
    $this->assertTrue(Schema::hasTable('likes'));
    $this->assertTrue(Schema::hasColumns('likes', [
        'id', 'liker_type', 'liker_id', 'likeable_type', 'likeable_id', 'created_at', 'updated_at',
    ]));
    $this->assertSame(1, DB::table('migrations')->where('migration', LIKES_MIGRATION)->count());
});

test('the unique index refuses a second row for a pair', function () {
    $row = ['liker_type' => 'user', 'liker_id' => '1', 'likeable_type' => 'post', 'likeable_id' => '1'];
    DB::table('likes')->insert($row);

    $this->expectException(QueryException::class);

    DB::table('likes')->insert($row);
});

test('the unique index allows the same ids with other types', function () {
    DB::table('likes')->insert(['liker_type' => 'user', 'liker_id' => '1', 'likeable_type' => 'post', 'likeable_id' => '1']);
    DB::table('likes')->insert(['liker_type' => 'user', 'liker_id' => '1', 'likeable_type' => 'comment', 'likeable_id' => '1']);
    DB::table('likes')->insert(['liker_type' => 'bot', 'liker_id' => '1', 'likeable_type' => 'post', 'likeable_id' => '1']);

    $this->assertSame(3, DB::table('likes')->count());
});

test('rollback drops the table and migrate creates it again', function () {
    runArtisan('migrate:rollback');
    $this->assertFalse(Schema::hasTable('likes'));

    runArtisan('migrate');
    $this->assertTrue(Schema::hasTable('likes'));
    $this->assertTrue(User::create()->like(Post::create()));
});

test('the configured table name is used by the migration and the model', function () {
    runArtisan('migrate:rollback');
    $this->app['config']->set('pharaonic.likes.table', 'post_likes');
    runArtisan('migrate');

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
});

test('an existing likes table stops the migration with an explanation', function () {
    runArtisan('migrate:rollback');

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
        runArtisan('migrate');
        $this->fail('The migration ran over an existing table.');
    } catch (RuntimeException $exception) {
        $this->assertStringContainsString('The [likes] table already exists', $exception->getMessage());
        $this->assertStringContainsString('upgrade guide', $exception->getMessage());
    }

    $this->assertSame(0, DB::table('migrations')->where('migration', LIKES_MIGRATION)->count());
});
