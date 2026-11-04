<?php

namespace Pharaonic\Laravel\Likes\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Likes\Concerns\CanLike;

/**
 * A liker that is not a user.
 */
class Bot extends Model
{
    use CanLike;

    /**
     * @var array<int, string>
     */
    protected $guarded = [];
}
