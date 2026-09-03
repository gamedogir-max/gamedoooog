<?php
/**
 * Minimal service-locator port used by the WordPress bootstrap.
 *
 * @package GameDog\PedigreeEngine\Application\Port
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Application\Port;

interface ContainerInterface
{
    /**
     * @param string $id
     *
     * @return mixed
     */
    public function get(string $id);

    public function has(string $id): bool;
}
