<?php

use Pharaonic\Laravel\Likes\Models\Like;
use Pharaonic\Laravel\Likes\Tests\Fixtures\Post;
use Pharaonic\Laravel\Likes\Tests\Fixtures\User;
use Symfony\Component\Process\Process;

/*
 * Separate PHP processes, each with its own connection, act on one pair at
 * the same moment. On SQLite the database file serializes the writes; on
 * MySQL and PostgreSQL (LIKES_DB) they really run in parallel.
 */

/**
 * Start eight workers on the pair at the same moment and collect what each returned.
 *
 * @param  array<string, string>  $env
 * @return array{0: User, 1: Post, 2: array<int, int>}
 */
function likeRace(array $env, string $action, bool $liked = false): array
{
    $user = User::create();
    $post = Post::create();

    if ($liked) {
        $user->like($post);
    }

    // Leave the workers time to boot, then let them all start together.
    $start = (string) (microtime(true) + 3);
    $processes = [];

    for ($i = 0; $i < 8; $i++) {
        $process = new Process(
            [PHP_BINARY, __DIR__.'/../Processes/worker.php', $action, (string) $user->getKey(), (string) $post->getKey(), $start],
            null,
            $env,
            null,
            60
        );
        $process->start();
        $processes[] = $process;
    }

    $results = [];

    foreach ($processes as $process) {
        $process->wait();

        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput().$process->getOutput())
            ->and($process->getOutput())->toBeIn(['0', '1']);

        $results[] = (int) $process->getOutput();
    }

    return [$user, $post, $results];
}

test('simultaneous likes create one row and one true', function () {
    [$user, $post, $results] = likeRace($this->useSharedDatabase(), 'like');

    expect(array_sum($results))->toBe(1)
        ->and(Like::query()->count())->toBe(1)
        ->and($user->hasLiked($post))->toBeTrue();
});

test('simultaneous unlikes delete the row once', function () {
    [$user, $post, $results] = likeRace($this->useSharedDatabase(), 'unlike', true);

    expect(array_sum($results))->toBe(1)
        ->and(Like::query()->count())->toBe(0)
        ->and($user->hasLiked($post))->toBeFalse();
});

test('simultaneous toggles never duplicate the like', function () {
    [$user, $post] = likeRace($this->useSharedDatabase(), 'toggle');

    expect(Like::query()->count())->toBeLessThanOrEqual(1)
        ->and($user->hasLiked($post))->toBe(Like::query()->count() === 1);
});
