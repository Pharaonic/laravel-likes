## Integrity and Concurrency

### The unique index

The `likes_unique_pair` index on (`likeable_type`, `likeable_id`, `liker_type`, `liker_id`) is what keeps a pair to one row. The package never reads first and writes second: `like()` inserts the row, and when the index refuses it because the pair already has one, `like()` returns false. Two requests that like the same post at the same moment therefore get `true` and `false`, and the table holds one row.

Only a refusal by a unique index counts as "already liked", and only when the pair's row can then be read. Every other database error is thrown: a missing table, a lost connection, a `NOT NULL` failure, or a unique index of your own that refused the row.

Inside a transaction the insert runs in a savepoint, so a refused insert doesn't abort your transaction on PostgreSQL.

### Removing

`unlike()` reads the pair's like and deletes it by id. The call that deleted the row returns true and dispatches `LikeRemoved`; a simultaneous `unlike()` deletes nothing and returns false.

### Toggling under concurrency

`toggleLike()` inserts first: when the insert works, the pair is now liked and the call returns true. When the pair already has a like, the call deletes it and returns false. No lock is held between the two steps.

What is guaranteed:

- the table never holds two rows for a pair;
- every call returns the state it left the pair in;
- events are dispatched only for changes the call made.

What is not: two toggles of the same pair at the same moment are not serialized. Starting from "liked", both may delete the same like, so the pair ends up not liked instead of liked again. When a user's intent matters, as in a like button, send the state the user wants and call `like()` or `unlike()`.

### Tested databases

The test suite runs on SQLite, MySQL 8.4 and PostgreSQL 16, including tests that start several PHP processes, each with its own connection, liking, unliking or toggling the same pair at the same moment. On SQLite the database file serializes those writes; MySQL and PostgreSQL run them in parallel.
