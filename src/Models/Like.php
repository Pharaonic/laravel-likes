<?php

namespace Pharaonic\Laravel\Likes\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Config;
use Pharaonic\Laravel\Likes\Support\LikeManager;

/**
 * A like given by a liker model to a likeable model.
 *
 * @property int $id
 * @property string $liker_type
 * @property string $liker_id
 * @property string $likeable_type
 * @property string $likeable_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Like extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'liker_type',
        'liker_id',
        'likeable_type',
        'likeable_id',
    ];

    /**
     * The table from the pharaonic.likes.table setting, unless a subclass sets $table.
     *
     * @return string
     */
    public function getTable()
    {
        if ($this->table) {
            return $this->table;
        }

        $table = Config::get('pharaonic.likes.table', 'likes');

        return is_string($table) && $table !== '' ? $table : 'likes';
    }

    /**
     * The model that gave the like.
     *
     * @return MorphTo<Model, $this>
     */
    public function liker(): MorphTo
    {
        return $this->morphTo('liker', 'liker_type', 'liker_id');
    }

    /**
     * The model that received the like.
     *
     * @return MorphTo<Model, $this>
     */
    public function likeable(): MorphTo
    {
        return $this->morphTo('likeable', 'likeable_type', 'likeable_id');
    }

    /**
     * Only the likes given by the liker.
     *
     * @param  Builder<Like>  $query
     */
    public function scopeWhereLiker(Builder $query, Model $liker): void
    {
        LikeManager::constrain($query, 'liker', $liker);
    }

    /**
     * Only the likes received by the likeable.
     *
     * @param  Builder<Like>  $query
     */
    public function scopeWhereLikeable(Builder $query, Model $likeable): void
    {
        LikeManager::constrain($query, 'likeable', $likeable);
    }
}
