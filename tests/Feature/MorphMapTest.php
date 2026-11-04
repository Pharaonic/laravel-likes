<?php

use Illuminate\Database\ClassMorphViolationException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Pharaonic\Laravel\Likes\Models\Like;
use Pharaonic\Laravel\Likes\Tests\Fixtures\Post;
use Pharaonic\Laravel\Likes\Tests\Fixtures\Product;
use Pharaonic\Laravel\Likes\Tests\Fixtures\User;

test('without a morph map the class names are stored', function () {
    User::create()->like(Post::create());

    $like = Like::query()->firstOrFail();

    $this->assertSame(User::class, $like->liker_type);
    $this->assertSame(Post::class, $like->likeable_type);
});

test('morph map aliases are stored and resolved', function () {
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
});

test('an enforced morph map refuses an unmapped model before writing', function () {
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
});
