<?php

namespace Pharaonic\Laravel\Likes\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Uses neither trait: a liker can still like it.
 */
class Product extends Model
{
    /**
     * @var array<int, string>
     */
    protected $guarded = [];
}
