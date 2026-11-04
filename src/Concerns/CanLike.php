<?php

namespace Pharaonic\Laravel\Likes\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Pharaonic\Laravel\Likes\Support\LikeManager;

/**
 * For a model that likes other models, such as a user.
 */
trait CanLike
{
    /**
     * Delete the likes the model gave when it is deleted (not soft deleted).
     */
    public static function bootCanLike(): void
    {
        static::deleted(function (Model $model) {
            LikeManager::cleanUp($model, 'liker');
        });
    }

    /**
     * The likes this model gave.
     */
    public function givenLikes(): MorphMany
    {
        return LikeManager::relation($this, 'liker');
    }

    /**
     * Like the model. True when this call created the like, false when it already existed.
     */
    public function like(Model $likeable): bool
    {
        return LikeManager::like($this, $likeable);
    }

    /**
     * Remove the like. True when this call removed it, false when there was none.
     */
    public function unlike(Model $likeable): bool
    {
        return LikeManager::unlike($this, $likeable);
    }

    /**
     * Whether this model likes the model.
     */
    public function hasLiked(Model $likeable): bool
    {
        return LikeManager::has($this, $likeable);
    }

    /**
     * Like the model, or remove the like. Returns the new state: true when liked.
     */
    public function toggleLike(Model $likeable): bool
    {
        return LikeManager::toggle($this, $likeable);
    }
}
