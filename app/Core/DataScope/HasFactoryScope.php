<?php

namespace App\Core\DataScope;

/**
 * Marks a model scoped by factory instead of production line — inventory
 * lives in warehouses that belong to a factory, with no line dimension.
 */
interface HasFactoryScope
{
    /**
     * The factory the row belongs to; null means unassigned and visible to
     * the `all` scope only.
     */
    public function dataScopeFactoryId(): ?int;
}
