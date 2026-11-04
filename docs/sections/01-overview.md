:::badges
- Laravel Package {color=blue}
- {release.label} {color=green}
- {package.license} License {color=purple}
:::

# Laravel Likes

Laravel Likes lets any Eloquent model like any other. Add `CanLike` to the model that likes, such as a user, and `HasLikes` to the model that is liked, such as a post. Each like is one row in a `likes` table, and a unique index on that table keeps every liker and likeable pair to one row, even when two requests try to create it at the same moment. `like()` and `unlike()` tell you whether they changed anything, and the likes are native Eloquent relations, so counts, filters and eager loading run in SQL.

:::features
### Results That Say What Changed {icon="check-circle"}
like and unlike return true only when the call created or removed the like.

### Unique by the Database {icon="lock"}
A unique index, not a read before the write, keeps each pair to one row.

### Any Model, Both Roles {icon="users"}
Users, bots or teams like posts, comments, products or each other, and one model can like and be liked.

### Native Relations {icon="chart"}
Count, filter and eager load likes with withCount, whereHas and with.

### Mixed Key Types {icon="tag"}
Integer, UUID and ULID models share one table, and morph map aliases are respected.

### Clean Deletes {icon="shield-check"}
Deleting a model deletes its likes, while a soft delete keeps them for a restore.
:::

:::info Quick Tip
Send the state the user wants (`like()` or `unlike()`) from your UI instead of `toggleLike()`: a double click then can't undo itself.
:::
