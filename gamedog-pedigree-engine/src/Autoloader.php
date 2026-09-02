<?php
/**
 * PSR-4 style autoloader for the GameDog Pedigree Engine namespace.
 *
 * @package GameDog\PedigreeEngine
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine;

final class Autoloader
{
    /** @var string */
    private static $baseDir = '';

    /** @var string */
    private static $prefix = 'GameDog\\PedigreeEngine\\';

    /**
     * Register the autoloader.
     *
     * @param string $baseDir Absolute path to the src directory.
     */
    public static function register(string $baseDir): void
    {
        self::$baseDir = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR;
        spl_autoload_register([self::class, 'load']);
    }

    /**
     * @param string $class Fully-qualified class name.
     */
    public static function load(string $class): void
    {
        if (strpos($class, self::$prefix) !== 0) {
            return;
        }

        $relative = substr($class, strlen(self::$prefix));
        $relative = str_replace('\\', DIRECTORY_SEPARATOR, $relative);
        $file     = self::$baseDir . $relative . '.php';

        if (is_file($file)) {
            require_once $file;
        }
    }
}
