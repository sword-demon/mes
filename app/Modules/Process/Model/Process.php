<?php

namespace App\Modules\Process\Model;

use Database\Factories\ProcessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $type
 * @property float $standard_hours
 * @property bool $requires_inspection
 * @property string|null $description
 * @property bool $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'name', 'type', 'standard_hours', 'requires_inspection', 'description', 'status'])]
#[Table('mes_processes')]
#[UseFactory(ProcessFactory::class)]
class Process extends Model
{
    /** @use HasFactory<ProcessFactory> */
    use HasFactory;

    /**
     * The types the process form offers; kept here so validation has one source.
     */
    public const array TYPES = ['准备', '加工', '检验', '包装'];

    /**
     * Mirrors the column defaults so freshly built models expose them.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'standard_hours' => 0,
        'requires_inspection' => false,
        'status' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'standard_hours' => 'float',
            'requires_inspection' => 'boolean',
            'status' => 'boolean',
        ];
    }
}
