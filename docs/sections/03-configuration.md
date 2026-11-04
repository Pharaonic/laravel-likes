## Configuration

Both settings are optional. Publish the file with `php artisan vendor:publish --tag=likes-config` to change them.

```php title="config/pharaonic/likes.php"
return [
    'table' => 'likes',
    'cleanup_on_delete' => true,
];
```

| Key | Default | Description |
| --- | --- | --- |
| `pharaonic.likes.table` | `likes` | The table the migration creates and the `Like` model uses. |
| `pharaonic.likes.cleanup_on_delete` | `true` | Delete a model's likes when the model is deleted. See [Deletes and Soft Deletes](#deletion-and-soft-deletes). |

### Changing the table name

The migration reads `pharaonic.likes.table` when it runs, so set the name **before** the first `php artisan migrate`:

```php title="config/pharaonic/likes.php"
'table' => 'post_likes',
```

Index names follow the table name (`post_likes_unique_pair`, `post_likes_liker_index`).

:::warning
Changing the setting after the migration ran does not rename the table: the package then reads and writes a table that doesn't exist. Rename the table in a migration of your own (`Schema::rename('likes', 'post_likes')`) at the same time as you change the setting. Rolling back the package migration drops the table the setting names at that moment.
:::
