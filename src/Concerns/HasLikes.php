<?php

namespace Pharaonic\Laravel\Likes\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Pharaonic\Laravel\Likes\Support\LikeManager;

/**
 * For a model that other models like, such as a post.
 */
trait HasLikes
{
    /**
     * Delete the likes the model received when it is deleted (not soft deleted).
     */
    public static function bootHasLikes(): void
    {
        static::deleted(function (Model $model) {
            LikeManager::cleanUp($model, 'likeable');
        });
    }

    /**
     * The likes this model received.
     */
    public function likes(): MorphMany
    {
        return LikeManager::relation($this, 'likeable');
    }

    /**
     * Whether the liker likes this model.
     */
    public function likedBy(Model $liker): bool
    {
        return LikeManager::has($liker, $this);
    }

    /**
     * Like this model as the liker. True when this call created the like.
     */
    public function likeBy(Model $liker): bool
    {
        return LikeManager::like($liker, $this);
    }

    /**
     * Remove the liker's like. True when this call removed it.
     */
    public function unlikeBy(Model $liker): bool
    {
        return LikeManager::unlike($liker, $this);
    }

    /**
     * Only the models the liker likes.
     */
    public function scopeWhereLikedBy(Builder $query, Model $liker): void
    {
        $query->whereHas('likes', function (Builder $likes) use ($liker) {
            LikeManager::constrain($likes, 'liker', $liker);
        });
    }
}
