<?php

// replace %s with gh username
const GH_USER_ACTIVITY_API_URL = 'https://api.github.com/users/%s/events';

/**
 * @throws JsonException
 */
function getUserActivityRaw(string $username): array
{
    $ch = curl_init(sprintf(GH_USER_ACTIVITY_API_URL, $username));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, $username);
    $result = curl_exec($ch);
    curl_close($ch);

    return json_decode($result, false, 512, JSON_THROW_ON_ERROR);
}


$username = $argv[1] ?? null;
if (empty($username)) {
    echo "⚠️ Username must be provided.";
    echo PHP_EOL;
    exit(1);
}

try {
    $userActivityRaw = getUserActivityRaw($username);
} catch (JsonException $e) {
    echo "❌ Invalid JSON";
    exit(2);
}

$events = new class () {
    // commits pushed to repo or tag
    public function pushEvent($userActivity): void {
        $nCommits = count($userActivity->payload->commits);
        echo "Pushed $nCommits commits to repo {$userActivity->repo->name}";
    }

    // branch or tag is created
    public function createEvent($userActivity): void
    {
        if ($userActivity->payload->ref_type === 'repository') {
            echo "Created repository '{$userActivity->repo->name}' with master branch as '{$userActivity->payload->master_branch}'";
        } else if ($userActivity->payload->ref_type === 'branch') {
            echo "Created branch '{$userActivity->payload->ref}' on repo {$userActivity->repo->name}";
        }
    }

    public function issueCommentEvent($userActivity): void
    {
        $user = $userActivity->payload->comment->user->login;
        $issue = $userActivity->payload->issue->number;
        echo "User $user commented on issue $issue for repo {$userActivity->repo->name}";
    }

    public function deleteEvent($userActivity): void
    {
        echo "Deleted {$userActivity->payload->ref_type} on {$userActivity->repo->name}";
    }

    public function pullRequestEvent($userActivity): void
    {
        $type = ucfirst($userActivity->payload->action);
        echo "$type PR '{$userActivity->payload->pull_request->title}' on repo {$userActivity->repo->name}";
    }
};

$eventsMap = [
    'PushEvent' => $events->pushEvent(...),
    'CreateEvent' => $events->createEvent(...),
    'IssueCommentEvent' => $events->issueCommentEvent(...),
    'DeleteEvent' => $events->deleteEvent(...),
    'PullRequestEvent' => $events->pullRequestEvent(...),
];

foreach ($userActivityRaw as $userActivity) {
    $method = $eventsMap[$userActivity->type] ?? null;
    if ($method === null) {
        continue;
    }
    echo "➡️ ";
    $method($userActivity);
    echo PHP_EOL;
}


