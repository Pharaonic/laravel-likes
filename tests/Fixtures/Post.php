<?php

namespace Pharaonic\Laravel\Likes\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Likes\Concerns\HasLikes;

class Post extends Model
{
    use HasLikes;

    /**
     * @var array<int, string>
     */
    protected $guarded = [];
}
