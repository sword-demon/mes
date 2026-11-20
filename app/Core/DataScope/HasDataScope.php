<?php

namespace App\Core\DataScope;

/**
 * Marks a model whose rows are scoped by factory and production line.
 *
 * The instance method answers "which production line does this row belong
 * to" and backs the write check; the `inDataScope` local scope applies the
 * same rule to queries. Models that hang off a task share one
 * implementation via the `ScopedThroughTask` trait; direct-line models
 * implement it themselves.
 */
interface HasDataScope
{
    /**
     * The production line the row belongs to; null when not placed on one.
     */
    public function dataScopeProductionLineId(): ?int;
}
