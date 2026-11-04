<?php

namespace Pharaonic\Laravel\Likes\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Pharaonic\Laravel\Likes\Concerns\HasLikes;

class SoftDeletablePost extends Model
{
    use HasLikes;
    use SoftDeletes;

    /**
     * @var array<int, string>
     */
    protected $guarded = [];
}
