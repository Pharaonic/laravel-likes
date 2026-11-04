<?php

namespace Pharaonic\Laravel\Likes\Events;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Likes\Models\Like;

/**
 * Dispatched once for each like this package deletes through unlike(),
 * unlikeBy() or toggleLike(), right after the delete. $like is the deleted row.
 */
final class LikeRemoved
{
    public function __construct(
        public Like $like,
        public Model $liker,
        public Model $likeable,
    ) {
    }
}
