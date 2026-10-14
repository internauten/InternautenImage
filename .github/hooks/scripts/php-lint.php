<?php
// PostToolUse hook: runs `php -l` on module PHP files the agent just edited.
// Reads the hook JSON from stdin; on syntax errors returns decision=block so the agent fixes them.

$input = json_decode((string) stream_get_contents(STDIN), true);
if (!is_array($input) || !isset($input['tool_input']) || !is_array($input['tool_input'])) {
    exit(0);
}

$root = dirname(__DIR__, 3);

// Collect every filePath/file_path value (covers single and multi-file edit tools).
$paths = [];
array_walk_recursive($input['tool_input'], function ($value, $key) use (&$paths) {
    if (($key === 'filePath' || $key === 'file_path') && is_string($value)) {
        $paths[] = $value;
    }
});

$errors = [];
foreach (array_unique($paths) as $path) {
    $real = realpath($path);
    if ($real === false || substr($real, -4) !== '.php') {
        continue;
    }
    if (strpos($real, $root . '/internautenimage/') !== 0 && strpos($real, $root . '/.github/') !== 0) {
        continue;
    }
    $output = [];
    $code = 0;
    exec('php -l ' . escapeshellarg($real) . ' 2>&1', $output, $code);
    if ($code !== 0) {
        $errors[] = implode("\n", $output);
    }
}

if (!empty($errors)) {
    echo json_encode([
        'decision' => 'block',
        'reason' => "PHP syntax check failed:\n" . implode("\n", $errors),
    ]);
}

exit(0);
