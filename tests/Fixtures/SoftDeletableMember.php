<?php

namespace Pharaonic\Laravel\Likes\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Pharaonic\Laravel\Likes\Concerns\CanLike;

class SoftDeletableMember extends Model
{
    use CanLike;
    use SoftDeletes;

    /**
     * @var array<int, string>
     */
    protected $guarded = [];
}
