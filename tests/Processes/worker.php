<?php

/*
 * One worker of the concurrency tests: boots the package, waits for the shared
 * start time, then likes, unlikes or toggles one pair and prints 1 or 0.
 *
 * Usage: php worker.php <like|unlike|toggle> <user id> <post id> <start time>
 */

use Pharaonic\Laravel\Likes\Tests\Fixtures\Post;
use Pharaonic\Laravel\Likes\Tests\Fixtures\User;
use Pharaonic\Laravel\Likes\Tests\Processes\WorkerApplication;

require __DIR__.'/../../vendor/autoload.php';

[, $action, $userId, $postId, $start] = $argv;

(new WorkerApplication('worker'))->createApplication();

$user = User::query()->findOrFail($userId);
$post = Post::query()->findOrFail($postId);

if ((float) $start > microtime(true)) {
    time_sleep_until((float) $start);
}

switch ($action) {
    case 'like':
        $result = $user->like($post);

        break;
    case 'unlike':
        $result = $user->unlike($post);

        break;
    default:
        $result = $user->toggleLike($post);
}

echo $result ? '1' : '0';
