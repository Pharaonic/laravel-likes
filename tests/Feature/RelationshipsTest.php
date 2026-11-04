<?php

namespace Pharaonic\Laravel\Likes\Tests\Feature;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;
use Pharaonic\Laravel\Likes\Models\Like;
use Pharaonic\Laravel\Likes\Tests\Fixtures\Bot;
use Pharaonic\Laravel\Likes\Tests\Fixtures\Comment;
use Pharaonic\Laravel\Likes\Tests\Fixtures\Post;
use Pharaonic\Laravel\Likes\Tests\Fixtures\User;
use Pharaonic\Laravel\Likes\Tests\TestCase;

class RelationshipsTest extends TestCase
{
    public function test_given_likes_and_likes_are_morph_many_relations(): void
    {
        $user = User::create();

        $this->assertInstanceOf(MorphMany::class, $user->givenLikes());
        $this->assertSame('liker_type', $user->givenLikes()->getMorphType());
        $this->assertSame('likes.liker_id', $user->givenLikes()->getQualifiedForeignKeyName());

        $this->assertInstanceOf(MorphMany::class, $user->likes());
        $this->assertSame('likeable_type', $user->likes()->getMorphType());
        $this->assertSame('likes.likeable_id', $user->likes()->getQualifiedForeignKeyName());
    }

    public function test_a_model_with_both_traits_keeps_given_and_received_likes_apart(): void
    {
        $alice = User::create();
        $bob = User::create();

        $alice->like($bob);

        $this->assertSame(1, $alice->givenLikes()->count());
        $this->assertSame(0, $alice->likes()->count());
        $this->assertSame(0, $bob->givenLikes()->count());
        $this->assertSame(1, $bob->likes()->count());
        $this->assertTrue($bob->likedBy($alice));
        $this->assertFalse($alice->likedBy($bob));
    }

    public function test_the_like_resolves_both_ends_through_morph_to(): void
    {
        $bot = Bot::create();
        $comment = Comment::create();
        $bot->like($comment);

        $like = Like::query()->firstOrFail();

        $this->assertInstanceOf(MorphTo::class, $like->liker());
        $this->assertInstanceOf(MorphTo::class, $like->likeable());
        $this->assertTrue($like->liker->is($bot));
        $this->assertTrue($like->likeable->is($comment));
    }

    public function test_likes_count_runs_a_count_query(): void
    {
        $post = Post::create();
        User::create()->like($post);
        User::create()->like($post);

        DB::enableQueryLog();
        $count = $post->likes()->count();
        $queries = DB::getQueryLog();

        $this->assertSame(2, $count);
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('count(*)', $queries[0]['query']);
        $this->assertFalse($post->relationLoaded('likes'));
    }

    public function test_with_count_and_load_count_count_the_likes(): void
    {
        [$first, $second] = [Post::create(), Post::create()];
        User::create()->like($first);
        User::create()->like($first);
        User::create()->like($second);
        Post::create();

        $counts = Post::withCount('likes')->orderBy('id')->get()->pluck('likes_count')->map(function ($value) {
            return (int) $value;
        })->all();
        $this->assertSame([2, 1, 0], $counts);

        $second->loadCount('likes');
        $this->assertSame(1, (int) $second->getAttribute('likes_count'));
    }

    public function test_eager_loading_likes_and_likers_takes_a_fixed_number_of_queries(): void
    {
        foreach (range(1, 3) as $i) {
            $post = Post::create();
            User::create()->like($post);
            Bot::create()->like($post);
        }

        DB::enableQueryLog();
        $posts = Post::with('likes.liker')->get();
        $names = $posts->flatMap(function (Post $post) {
            return $post->likes->map(function (Like $like) {
                return get_class($like->liker);
            });
        });

        // posts, likes, users, bots.
        $this->assertCount(4, DB::getQueryLog());
        $this->assertCount(6, $names);
    }

    public function test_given_likes_eager_load_their_likeables(): void
    {
        $user = User::create();
        $user->like(Post::create());
        $user->like(Comment::create());

        $likes = $user->givenLikes()->with('likeable')->latest()->get();

        $this->assertCount(2, $likes);
        $this->assertEqualsCanonicalizing(
            [Post::class, Comment::class],
            $likes->map(function (Like $like) {
                return get_class($like->likeable);
            })->all()
        );
    }

    public function test_where_liked_by_keeps_only_the_models_the_liker_likes(): void
    {
        $alice = User::create();
        $bob = User::create();
        [$first, $second, $third] = [Post::create(), Post::create(), Post::create()];

        $alice->like($first);
        $alice->like($third);
        $bob->like($second);
        Bot::create()->like($second);

        $this->assertSame([$first->id, $third->id], Post::whereLikedBy($alice)->orderBy('id')->pluck('id')->map(function ($value) {
            return (int) $value;
        })->all());
        $this->assertSame([$second->id], Post::whereLikedBy($bob)->pluck('id')->map(function ($value) {
            return (int) $value;
        })->all());
        $this->assertSame(0, Comment::whereLikedBy($alice)->count());
    }

    public function test_a_feed_can_flag_what_the_viewer_liked_in_the_same_query(): void
    {
        $viewer = User::create();
        [$first, $second] = [Post::create(), Post::create()];
        $viewer->like($second);
        User::create()->like($first);

        $posts = Post::withCount([
            'likes',
            'likes as liked_by_viewer' => function ($query) use ($viewer) {
                $query->whereLiker($viewer);
            },
        ])->orderBy('id')->get();

        $this->assertSame([0, 1], $posts->pluck('liked_by_viewer')->map(function ($value) {
            return (int) $value;
        })->all());
        $this->assertSame([1, 1], $posts->pluck('likes_count')->map(function ($value) {
            return (int) $value;
        })->all());
    }

    public function test_like_scopes_filter_by_liker_and_likeable(): void
    {
        $user = User::create();
        $post = Post::create();
        $user->like($post);
        Bot::create()->like($post);

        $this->assertSame(1, Like::whereLiker($user)->count());
        $this->assertSame(2, Like::whereLikeable($post)->count());
        $this->assertSame(1, User::whereHas('givenLikes', function ($query) use ($post) {
            $query->whereLikeable($post);
        })->count());
    }
}
