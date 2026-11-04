<?php

namespace Pharaonic\Laravel\Likes\Events;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Likes\Models\Like;

/**
 * Dispatched once for each like this package inserts, right after the insert.
 */
final class LikeCreated
{
    public function __construct(
        public Like $like,
        public Model $liker,
        public Model $likeable,
    ) {
    }
}
