<?php

namespace App\Modules\Quality\Model;

use Database\Factories\DefectCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $category
 * @property bool $status
 */
#[Fillable(['code', 'name', 'category', 'status'])]
#[Table('mes_defect_codes')]
#[UseFactory(DefectCodeFactory::class)]
class DefectCode extends Model
{
    /** @use HasFactory<DefectCodeFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => 'boolean'];
    }
}
