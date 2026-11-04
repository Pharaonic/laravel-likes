<?php

namespace Pharaonic\Laravel\Likes\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Pharaonic\Laravel\Likes\Concerns\CanLike;
use Pharaonic\Laravel\Likes\Concerns\HasLikes;

/**
 * Likes other models and is liked too.
 */
class User extends Authenticatable
{
    use CanLike;
    use HasLikes;

    /**
     * @var array<int, string>
     */
    protected $guarded = [];
}
