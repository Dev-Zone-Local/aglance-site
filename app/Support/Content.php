<?php

namespace App\Support;

/**
 * JSON content files in content/ (roadmap.json, cli-commands.json). Edit a file to change the
 * page; no code change needed. A missing or broken file gives an empty array.
 */
class Content
{
    public static function json(string $name): array
    {
        $path = base_path("content/{$name}.json");
        if (! is_file($path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }
}
