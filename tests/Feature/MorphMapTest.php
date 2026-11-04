<?php

namespace Pharaonic\Laravel\Likes\Tests\Feature;

use Illuminate\Database\ClassMorphViolationException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Pharaonic\Laravel\Likes\Models\Like;
use Pharaonic\Laravel\Likes\Tests\Fixtures\Post;
use Pharaonic\Laravel\Likes\Tests\Fixtures\Product;
use Pharaonic\Laravel\Likes\Tests\Fixtures\User;
use Pharaonic\Laravel\Likes\Tests\TestCase;

class MorphMapTest extends TestCase
{
    public function test_without_a_morph_map_the_class_names_are_stored(): void
    {
        User::create()->like(Post::create());

        $like = Like::query()->firstOrFail();

        $this->assertSame(User::class, $like->liker_type);
        $this->assertSame(Post::class, $like->likeable_type);
    }

    public function test_morph_map_aliases_are_stored_and_resolved(): void
    {
        Relation::morphMap(['user' => User::class, 'post' => Post::class]);

        $user = User::create();
        $post = Post::create();
        $user->like($post);

        $like = Like::query()->firstOrFail();

        $this->assertSame('user', $like->liker_type);
        $this->assertSame('post', $like->likeable_type);
        $this->assertTrue($like->liker->is($user));
        $this->assertTrue($like->likeable->is($post));
        $this->assertTrue($post->likedBy($user));
        $this->assertSame(1, Post::whereLikedBy($user)->count());
        $this->assertSame(1, (int) Post::withCount('likes')->firstOrFail()->getAttribute('likes_count'));
    }

    public function test_an_enforced_morph_map_refuses_an_unmapped_model_before_writing(): void
    {
        if (! method_exists(Relation::class, 'enforceMorphMap')) {
            $this->markTestSkipped('Relation::enforceMorphMap() needs Laravel 8.');
        }

        Relation::enforceMorphMap(['user' => User::class, 'post' => Post::class]);

        $user = User::create();
        $this->assertTrue($user->like(Post::create()));

        try {
            $user->like(Product::create());
            $this->fail('An unmapped model was liked.');
        } catch (ClassMorphViolationException $exception) {
            $this->assertStringContainsString(Product::class, $exception->getMessage());
        }

        $this->assertSame(1, Like::query()->count());
    }
}
