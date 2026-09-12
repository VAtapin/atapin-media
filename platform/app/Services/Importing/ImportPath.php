<?php
namespace App\Services\Importing;
use RuntimeException;
class ImportPath
{
    public static function resolve(string $root, string $path): string
    {
        $root = realpath($root);
        if (! $root || str_contains($path, "\0")) throw new RuntimeException('Import directory is unavailable.');
        $absolute = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path);
        $real = realpath($absolute ? $path : $root.DIRECTORY_SEPARATOR.$path);
        if (! $real || ($real !== $root && ! str_starts_with($real, $root.DIRECTORY_SEPARATOR))) {
            throw new RuntimeException('Source is outside the permitted import directory.');
        }
        return $real;
    }
    public static function entry(string $name): string
    {
        $name = str_replace('\\', '/', $name);
        if ($name === '' || str_contains($name, "\0") || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name)
            || in_array('..', explode('/', $name), true)) throw new RuntimeException('Unsafe archive entry.');
        return $name;
    }
}
