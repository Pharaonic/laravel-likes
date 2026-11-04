<?php

namespace Pharaonic\Laravel\Likes\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Pharaonic\Laravel\Likes\Events\LikeCreated;
use Pharaonic\Laravel\Likes\Events\LikeRemoved;
use Pharaonic\Laravel\Likes\Models\Like;
use Pharaonic\Laravel\Likes\Relations\LikesMorphMany;
use Stringable;

/**
 * The one place that reads and writes likes, shared by CanLike and HasLikes.
 *
 * @internal
 */
final class LikeManager
{
    /**
     * The longest morph type the likes table stores.
     */
    public const TYPE_LENGTH = 191;

    /**
     * The longest key the likes table stores.
     */
    public const KEY_LENGTH = 64;

    /**
     * How many times an insert runs when the pair's like keeps vanishing.
     */
    private const INSERT_ATTEMPTS = 3;

    /**
     * Like the likeable. True when this call created the like.
     */
    public static function like(Model $liker, Model $likeable): bool
    {
        return self::insert($liker, $likeable)[1];
    }

    /**
     * Remove the like. True when this call deleted it.
     */
    public static function unlike(Model $liker, Model $likeable): bool
    {
        $like = Like::query()->useWritePdo()->where(self::pair($liker, $likeable))->first();

        return $like instanceof Like && self::remove($like, $liker, $likeable);
    }

    /**
     * Like the likeable, or remove the like when it exists. Returns the new state.
     *
     * The insert comes first, so the unique index rather than a read decides
     * which branch runs; no lock is held between the two steps.
     */
    public static function toggle(Model $liker, Model $likeable): bool
    {
        [$like, $created] = self::insert($liker, $likeable);

        if ($created) {
            return true;
        }

        if ($like === null) {
            return self::has($liker, $likeable);
        }

        self::remove($like, $liker, $likeable);

        return false;
    }

    /**
     * Whether the liker likes the likeable. False when either one is unsaved.
     */
    public static function has(Model $liker, Model $likeable): bool
    {
        if (! self::isPersisted($liker) || ! self::isPersisted($likeable)) {
            return false;
        }

        return Like::query()->where(self::pair($liker, $likeable))->exists();
    }

    /**
     * The likes given (liker) or received (likeable) by the model.
     */
    public static function relation(Model $parent, string $side): MorphMany
    {
        $like = new Like;
        $table = $like->getTable();

        return new LikesMorphMany(
            $like->newQuery(),
            $parent,
            $table.'.'.$side.'_type',
            $table.'.'.$side.'_id',
            $parent->getKeyName()
        );
    }

    /**
     * Limit a likes query to one liker or likeable. An unsaved model matches nothing.
     */
    public static function constrain(Builder $query, string $side, Model $model): void
    {
        if (! self::isPersisted($model)) {
            $query->whereRaw('0 = 1');

            return;
        }

        [$type, $key] = self::endpoint($model, $side);

        $query->where($query->qualifyColumn($side.'_type'), $type);
        $query->where($query->qualifyColumn($side.'_id'), $key);
    }

    /**
     * Delete the likes of a model that is being deleted, unless it is only soft deleted.
     */
    public static function cleanUp(Model $model, string $side): void
    {
        if (! (bool) Config::get('pharaonic.likes.cleanup_on_delete', true)) {
            return;
        }

        if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
            return;
        }

        $key = self::key($model);

        if ($key === null) {
            return;
        }

        Like::query()->toBase()
            ->where($side.'_type', $model->getMorphClass())
            ->where($side.'_id', $key)
            ->delete();
    }

    /**
     * Whether the database refused a row because of a unique index.
     */
    public static function isUniqueViolation(QueryException $exception): bool
    {
        $errorInfo = is_array($exception->errorInfo) ? $exception->errorInfo : [];
        $sqlState = isset($errorInfo[0]) && is_string($errorInfo[0]) ? $errorInfo[0] : (string) $exception->getCode();
        $driverCode = $errorInfo[1] ?? null;

        // PostgreSQL: unique_violation.
        if ($sqlState === '23505') {
            return true;
        }

        // MySQL and MariaDB: ER_DUP_ENTRY, ER_DUP_ENTRY_WITH_KEY_NAME.
        // SQL Server: duplicate key in a unique index or constraint.
        if (in_array($driverCode, [1062, 1586, 2601, 2627], true)) {
            return true;
        }

        // SQLite reports every constraint failure as code 19.
        return str_contains($exception->getMessage(), 'UNIQUE constraint failed');
    }

    /**
     * Insert the like, unless the pair already has one.
     *
     * @return array{0: Like|null, 1: bool} the like (null when a model event
     *                                      cancelled the insert), and whether this call created it
     */
    private static function insert(Model $liker, Model $likeable): array
    {
        $pair = self::pair($liker, $likeable);

        for ($attempt = 1; ; $attempt++) {
            $like = new Like($pair);
            $connection = $like->getConnection();

            try {
                // Inside a transaction, a savepoint keeps a refused insert from
                // aborting the caller's transaction (PostgreSQL aborts it otherwise).
                $saved = $connection->transactionLevel() > 0
                    ? $connection->transaction(function () use ($like) {
                        return $like->save();
                    })
                    : $like->save();
            } catch (QueryException $exception) {
                if (! self::isUniqueViolation($exception)) {
                    throw $exception;
                }

                $existing = Like::query()->useWritePdo()->where($pair)->first();

                if ($existing instanceof Like) {
                    return [$existing, false];
                }

                // The like was deleted between the insert and the read: insert
                // again. When it keeps missing, another unique index refused the
                // row, which is not ours to swallow.
                if ($attempt >= self::INSERT_ATTEMPTS) {
                    throw $exception;
                }

                continue;
            }

            if (! $saved) {
                return [null, false];
            }

            Event::dispatch(new LikeCreated($like, $liker, $likeable));

            return [$like, true];
        }
    }

    /**
     * Delete the like. True when this call deleted the row.
     */
    private static function remove(Like $like, Model $liker, Model $likeable): bool
    {
        $deleted = Like::query()->toBase()->where($like->getKeyName(), $like->getKey())->delete() > 0;

        if ($deleted) {
            $like->exists = false;

            Event::dispatch(new LikeRemoved($like, $liker, $likeable));
        }

        return $deleted;
    }

    /**
     * The four columns that identify the like of a pair.
     *
     * @return array{liker_type: string, liker_id: string, likeable_type: string, likeable_id: string}
     */
    private static function pair(Model $liker, Model $likeable): array
    {
        [$likerType, $likerId] = self::endpoint($liker, 'liker');
        [$likeableType, $likeableId] = self::endpoint($likeable, 'likeable');

        return [
            'liker_type' => $likerType,
            'liker_id' => $likerId,
            'likeable_type' => $likeableType,
            'likeable_id' => $likeableId,
        ];
    }

    /**
     * The morph type and key the likes table stores for the model.
     *
     * @return array{0: string, 1: string}
     *
     * @throws InvalidArgumentException when the model is unsaved, or its type or key does not fit
     */
    private static function endpoint(Model $model, string $side): array
    {
        if (! $model->exists) {
            throw new InvalidArgumentException(sprintf(
                'The %s [%s] is not saved: save it before it likes or is liked.', $side, get_class($model)
            ));
        }

        $key = self::key($model);

        if ($key === null) {
            throw new InvalidArgumentException(sprintf(
                'The %s [%s] has no usable primary key.', $side, get_class($model)
            ));
        }

        if (mb_strlen($key) > self::KEY_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'The key of the %s [%s] is %d characters long; the likes table stores keys of up to %d characters.',
                $side, get_class($model), mb_strlen($key), self::KEY_LENGTH
            ));
        }

        $type = $model->getMorphClass();

        if (mb_strlen($type) > self::TYPE_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'The morph type of the %s [%s] is %d characters long; the likes table stores types of up to %d characters. '
                .'Register a shorter alias with Relation::morphMap().',
                $side, get_class($model), mb_strlen($type), self::TYPE_LENGTH
            ));
        }

        return [$type, $key];
    }

    /**
     * Whether the model is saved and has a usable key.
     */
    private static function isPersisted(Model $model): bool
    {
        return $model->exists && self::key($model) !== null;
    }

    /**
     * The model key as the string the likes table stores, or null when it has none.
     */
    private static function key(Model $model): ?string
    {
        $key = $model->getKey();

        if (! is_int($key) && ! is_string($key) && ! $key instanceof Stringable) {
            return null;
        }

        $key = (string) $key;

        return $key === '' ? null : $key;
    }
}
