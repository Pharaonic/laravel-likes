<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Pharaonic\Laravel\Likes\Models\Like;

/**
 * One row per liker and likeable pair. The ids are strings, so integer,
 * UUID and ULID keys can share the table, and the unique index is what
 * keeps a pair from being liked twice, even under concurrent requests.
 */
class CreatePharaonicLikesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $name = (new Like)->getTable();

        if (Schema::hasTable($name)) {
            throw new RuntimeException(sprintf(
                'The [%s] table already exists, so pharaonic/laravel-likes cannot create it. '
                .'If pharaonic/laravel-users created it, convert it as the upgrade guide describes '
                .'(https://pharaonic.dev/packages/laravel/likes); otherwise set pharaonic.likes.table to another name.',
                $name
            ));
        }

        Schema::create($name, function (Blueprint $table) use ($name) {
            $table->bigIncrements('id');
            $table->string('liker_type', 191);
            $table->string('liker_id', 64);
            $table->string('likeable_type', 191);
            $table->string('likeable_id', 64);
            $table->timestamps();

            // Also serves "likes of this model": its first two columns.
            $table->unique(['likeable_type', 'likeable_id', 'liker_type', 'liker_id'], $name.'_unique_pair');
            $table->index(['liker_type', 'liker_id'], $name.'_liker_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists((new Like)->getTable());
    }
}
