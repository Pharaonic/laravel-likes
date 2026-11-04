<?php

namespace Pharaonic\Laravel\Likes\Tests\Feature;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Pharaonic\Laravel\Likes\Models\Like;
use Pharaonic\Laravel\Likes\Tests\Fixtures\Bot;
use Pharaonic\Laravel\Likes\Tests\Fixtures\Comment;
use Pharaonic\Laravel\Likes\Tests\Fixtures\Post;
use Pharaonic\Laravel\Likes\Tests\Fixtures\Product;
use Pharaonic\Laravel\Likes\Tests\Fixtures\User;
use Pharaonic\Laravel\Likes\Tests\Fixtures\UuidModel;
use Pharaonic\Laravel\Likes\Tests\TestCase;

class LikingTest extends TestCase
{
    public function test_like_returns_true_only_when_it_creates_the_like(): void
    {
        $user = User::create();
        $post = Post::create();

        $this->assertTrue($user->like($post));
        $this->assertFalse($user->like($post));
        $this->assertFalse($user->like($post));
        $this->assertSame(1, Like::query()->count());
    }

    public function test_unlike_returns_true_only_when_it_removes_the_like(): void
    {
        $user = User::create();
        $post = Post::create();

        $this->assertFalse($user->unlike($post));

        $user->like($post);

        $this->assertTrue($user->unlike($post));
        $this->assertFalse($user->unlike($post));
        $this->assertSame(0, Like::query()->count());
    }

    public function test_has_liked_and_liked_by_report_the_current_state(): void
    {
        $user = User::create();
        $post = Post::create();

        $this->assertFalse($user->hasLiked($post));
        $this->assertFalse($post->likedBy($user));

        $user->like($post);

        $this->assertTrue($user->hasLiked($post));
        $this->assertTrue($post->likedBy($user));
    }

    public function test_like_by_and_unlike_by_return_whether_they_changed_anything(): void
    {
        $user = User::create();
        $post = Post::create();

        $this->assertTrue($post->likeBy($user));
        $this->assertFalse($post->likeBy($user));
        $this->assertTrue($post->unlikeBy($user));
        $this->assertFalse($post->unlikeBy($user));
    }

    public function test_both_sides_work_on_the_same_row(): void
    {
        $user = User::create();
        $post = Post::create();

        $user->like($post);
        $this->assertTrue($post->likedBy($user));
        $this->assertFalse($post->likeBy($user));

        $this->assertTrue($post->unlikeBy($user));
        $this->assertFalse($user->hasLiked($post));

        $this->assertTrue($post->likeBy($user));
        $this->assertTrue($user->hasLiked($post));
        $this->assertTrue($user->unlike($post));
        $this->assertSame(0, Like::query()->count());
    }

    public function test_the_legacy_un_like_by_spelling_calls_unlike_by(): void
    {
        $user = User::create();
        $post = Post::create();
        $post->likeBy($user);

        // PHP method names are case-insensitive.
        $this->assertTrue($post->unLikeBy($user));
    }

    public function test_toggle_like_returns_the_new_state(): void
    {
        $user = User::create();
        $post = Post::create();

        $this->assertTrue($user->toggleLike($post));
        $this->assertSame(1, Like::query()->count());

        $this->assertFalse($user->toggleLike($post));
        $this->assertSame(0, Like::query()->count());

        $this->assertTrue($user->toggleLike($post));
        $this->assertTrue($user->hasLiked($post));
        $this->assertSame(1, Like::query()->count());
    }

    public function test_a_liker_can_like_any_model(): void
    {
        $user = User::create();
        $other = User::create();

        $this->assertTrue($user->like(Post::create()));
        $this->assertTrue($user->like(Comment::create()));
        $this->assertTrue($user->like(Product::create()));
        $this->assertTrue($user->like($other));
        $this->assertTrue($user->like($user));

        $this->assertSame(5, $user->givenLikes()->count());
        $this->assertTrue($other->likedBy($user));
        $this->assertTrue($user->likedBy($user));
    }

    public function test_a_post_and_a_comment_with_the_same_id_are_different_targets(): void
    {
        $user = User::create();
        $post = Post::create();
        $comment = Comment::create();
        $this->assertSame($post->getKey(), $comment->getKey());

        $user->like($post);

        $this->assertTrue($user->hasLiked($post));
        $this->assertFalse($user->hasLiked($comment));
        $this->assertTrue($user->like($comment));
    }

    public function test_many_likers_can_like_many_targets(): void
    {
        $alice = User::create();
        $bob = User::create();
        $first = Post::create();
        $second = Post::create();

        $this->assertTrue($alice->like($first));
        $this->assertTrue($bob->like($first));
        $this->assertTrue($alice->like($second));

        $this->assertSame(2, $first->likes()->count());
        $this->assertSame(1, $second->likes()->count());
        $this->assertSame(2, $alice->givenLikes()->count());
        $this->assertSame(1, $bob->givenLikes()->count());
    }

    public function test_the_liker_does_not_have_to_be_a_user_or_signed_in(): void
    {
        $bot = Bot::create();
        $post = Post::create();

        $this->assertFalse(Auth::check());
        $this->assertTrue($bot->like($post));
        $this->assertTrue($post->likedBy($bot));
        $this->assertInstanceOf(Bot::class, $post->likes()->firstOrFail()->liker);
    }

    public function test_writes_reject_an_unsaved_liker_without_creating_a_row(): void
    {
        $post = Post::create();

        foreach (['like', 'unlike', 'toggleLike'] as $method) {
            try {
                (new User)->{$method}($post);
                $this->fail("{$method}() accepted an unsaved liker.");
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString('The liker ['.User::class.'] is not saved', $exception->getMessage());
            }
        }

        $this->expectException(InvalidArgumentException::class);

        try {
            $post->likeBy(new User);
        } finally {
            $this->assertSame(0, Like::query()->count());
        }
    }

    public function test_writes_reject_an_unsaved_likeable_without_creating_a_row(): void
    {
        $user = User::create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The likeable ['.Post::class.'] is not saved');

        try {
            $user->like(new Post);
        } finally {
            $this->assertSame(0, Like::query()->count());
        }
    }

    public function test_reads_treat_an_unsaved_model_as_not_liked(): void
    {
        $user = User::create();
        $post = Post::create();
        $user->like($post);

        $this->assertFalse($user->hasLiked(new Post));
        $this->assertFalse($post->likedBy(new User));
        $this->assertSame(0, Post::whereLikedBy(new User)->count());
    }

    public function test_a_key_longer_than_the_column_is_rejected(): void
    {
        $target = new UuidModel;
        $target->exists = true;
        $target->id = str_repeat('a', 65);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is 65 characters long; the likes table stores keys of up to 64 characters');

        try {
            User::create()->like($target);
        } finally {
            $this->assertSame(0, Like::query()->count());
        }
    }

    public function test_a_morph_type_longer_than_the_column_is_rejected(): void
    {
        Relation::morphMap([str_repeat('p', 192) => Post::class]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Register a shorter alias with Relation::morphMap()');

        User::create()->like(Post::create());
    }
}
