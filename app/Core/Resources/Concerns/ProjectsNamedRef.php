<?php

namespace App\Core\Resources\Concerns;

trait ProjectsNamedRef
{
    /**
     * The three fields every dropdown needs from a parent record.
     *
     * @param  (object{id: int, code: string, name: string})|null  $model
     * @return array{id: int, code: string, name: string}|null
     */
    protected function namedRef(?object $model): ?array
    {
        return $model === null ? null : [
            'id' => $model->id,
            'code' => $model->code,
            'name' => $model->name,
        ];
    }
}
