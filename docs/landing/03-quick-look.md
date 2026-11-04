---
view: components.packages.quick-look
title: A quick look
subtitle: A user likes a post, and the post page shows its count.
file: routes/web.php
language: php
code: |
  use App\Models\Post;

  Route::post('posts/{post}/like', function (Post $post) {
      $created = request()->user()->like($post);

      return response()->json([
          'created' => $created,              // false when it was already liked
          'likes' => $post->likes()->count(),
      ]);
  })->middleware('auth');

  Route::get('posts', function () {
      return Post::withCount('likes')->latest()->paginate();
  });
---
