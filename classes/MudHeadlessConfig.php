<?php

declare(strict_types=1);

namespace Grav\Plugin\GravMudAlpha;

use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;

/**
 * Resolve headless plugin settings. Grav's compiled config can lag behind FTP uploads
 * or drop keys when blueprints are stale — read user/config/plugins/*.yaml directly.
 */
final class MudHeadlessConfig
{
    /** @return array<string, mixed> */
    public static function resolve(Grav $grav, ?array $runtime = null): array
    {
        $runtime ??= (array) $grav['config']->get('plugins.grav-mud-alpha', []);
        $fromFile = self::readUserYaml($grav);

        return array_replace($runtime, $fromFile);
    }

    /** @return array{path: string, readable: bool, mtime: int|null, keys: list<string>} */
    public static function fileMeta(Grav $grav): array
    {
        $path = self::userConfigPath($grav);

        return [
            'path' => $path,
            'readable' => $path !== '' && is_readable($path),
            'mtime' => ($path !== '' && is_readable($path)) ? (int) filemtime($path) : null,
            'keys' => array_keys(self::readUserYaml($grav)),
        ];
    }

    /** @return array<string, mixed> */
    private static function readUserYaml(Grav $grav): array
    {
        $path = self::userConfigPath($grav);
        if ($path === '' || !is_readable($path)) {
            return [];
        }

        try {
            $file = CompiledYamlFile::instance($path);
            $data = (array) $file->content();
            $file->free();

            return $data;
        } catch (\Throwable) {
            return [];
        }
    }

    private static function userConfigPath(Grav $grav): string
    {
        try {
            $path = $grav['locator']->findResource('user://config/plugins/grav-mud-alpha.yaml', true, true);

            return is_string($path) ? $path : '';
        } catch (\Throwable) {
            return '';
        }
    }
}
