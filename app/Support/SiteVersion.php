<?php

namespace App\Support;

/**
 * The website's version, shown at the bottom of the admin sidebar: the number in the VERSION file at the
 * repo root (bump it per release) plus the deployed git commit and branch, read straight from
 * .git (no shell). Without .git (e.g. a Docker image) only the number is known.
 */
class SiteVersion
{
    /** @var array{version: string, commit: ?string, branch: ?string}|null */
    private static ?array $current = null;

    /** @return array{version: string, commit: ?string, branch: ?string} */
    public static function current(): array
    {
        return self::$current ??= self::read(base_path());
    }

    /** "2.1.0 · build 5352f9f (Version2.1)", or just "2.1.0". */
    public static function label(?array $v = null): string
    {
        $v ??= self::current();
        $label = $v['version'];

        if ($v['commit']) {
            $label .= ' · build '.$v['commit'].($v['branch'] ? ' ('.$v['branch'].')' : '');
        }

        return $label;
    }

    /** @return array{version: string, commit: ?string, branch: ?string} */
    public static function read(string $root): array
    {
        $file = $root.'/VERSION';
        $version = is_file($file) ? trim((string) file_get_contents($file)) : '';

        [$commit, $branch] = self::gitHead($root.'/.git');

        return ['version' => $version !== '' ? $version : 'dev', 'commit' => $commit, 'branch' => $branch];
    }

    /** @return array{0: ?string, 1: ?string} short commit hash and branch name */
    private static function gitHead(string $git): array
    {
        $head = is_file($git.'/HEAD') ? trim((string) file_get_contents($git.'/HEAD')) : '';

        if (preg_match('/^[0-9a-f]{40}$/', $head)) {
            return [substr($head, 0, 7), null]; // detached HEAD
        }
        if (! str_starts_with($head, 'ref: ')) {
            return [null, null];
        }

        $ref = substr($head, 5);
        $branch = str_starts_with($ref, 'refs/heads/') ? substr($ref, 11) : null;
        $hash = is_file($git.'/'.$ref) ? trim((string) file_get_contents($git.'/'.$ref)) : self::packedRef($git, $ref);

        return [preg_match('/^[0-9a-f]{40}$/', (string) $hash) ? substr($hash, 0, 7) : null, $branch];
    }

    private static function packedRef(string $git, string $ref): ?string
    {
        if (! is_file($git.'/packed-refs')) {
            return null;
        }

        foreach (file($git.'/packed-refs', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (str_ends_with($line, ' '.$ref)) {
                return strtok($line, ' ') ?: null;
            }
        }

        return null;
    }
}
