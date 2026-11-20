<?php

namespace App\Core\DataScope;

/**
 * Marks a model whose rows are scoped by factory and production line.
 *
 * The instance method answers "which production line does this row belong
 * to" and backs the write check; the `inDataScope` local scope applies the
 * same rule to queries and is implemented per model, because how a row
 * reaches a line differs per table.
 */
interface HasDataScope
{
    /**
     * The production line the row belongs to; null when not placed on one.
     */
    public function dataScopeProductionLineId(): ?int;
}
