<?php

namespace App\Core\Models\Concerns;

trait HasStatusLabels
{
    /**
     * The using model owns STATUS_LABELS, so the trait resolves it at runtime
     * instead of referencing a constant it cannot declare itself.
     */
    public function statusLabel(): string
    {
        $labels = constant(static::class.'::STATUS_LABELS');

        return $labels[$this->status] ?? $this->status;
    }
}
