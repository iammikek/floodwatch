<?php

$src = __DIR__.'/pre-commit';
$dst = __DIR__.'/../.git/hooks/pre-commit';

if (! is_dir(__DIR__.'/../.git')) {
    echo "Not a git repository, skipping.\n";

    return;
}

copy($src, $dst);
chmod($dst, 0755);
echo "Pre-commit hook installed.\n";
