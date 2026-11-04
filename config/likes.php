<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Likes table
    |--------------------------------------------------------------------------
    |
    | The table that stores every like. The package migration creates it with
    | this name, and the Like model reads and writes it. Changing it after the
    | migration ran does not rename the existing table.
    |
    */

    'table' => 'likes',

    /*
    |--------------------------------------------------------------------------
    | Clean up on delete
    |--------------------------------------------------------------------------
    |
    | When a model that uses CanLike or HasLikes is deleted (or force deleted,
    | when it uses SoftDeletes), delete the likes it gave or received. A soft
    | delete always keeps them, so a restored model keeps its likes.
    |
    */

    'cleanup_on_delete' => true,

];
