<?php

declare(strict_types=1);

namespace Tests\Support;

use Psr\Container\ContainerInterface;

/** Minimal PSR-11 container that news up handler classes (no constructor deps). */
final class SimpleContainer implements ContainerInterface
{
    public function get(string $id): object
    {
        return new $id();
    }

    public function has(string $id): bool
    {
        return class_exists($id);
    }
}
