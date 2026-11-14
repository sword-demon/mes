<?php

namespace App\Modules\Bom\Model;

use App\Core\Exceptions\ApiException;
use App\Modules\Product\Model\Product;
use App\Modules\Product\Model\ProductVersion;
use Database\Factories\BomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property int $product_id
 * @property int $product_version_id
 * @property string $version
 * @property string $audit_status
 * @property Carbon|null $audited_at
 * @property string|null $remark
 * @property bool $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'product_id', 'product_version_id', 'version', 'audit_status', 'audited_at', 'remark', 'status'])]
#[Table('mes_boms')]
#[UseFactory(BomFactory::class)]
class Bom extends Model
{
    /** @use HasFactory<BomFactory> */
    use HasFactory;

    public const string DRAFT = 'draft';

    public const string PENDING = 'pending';

    public const string APPROVED = 'approved';

    /**
     * A lock only stops content changes; a retired BOM must still be disable-able.
     */
    public const array FIELDS_ALLOWED_WHEN_LOCKED = ['status'];

    /**
     * Mirrors the column defaults so freshly built models expose them.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'audit_status' => self::DRAFT,
        'status' => true,
    ];

    /**
     * Only a draft may still change; submitting locks the bill of materials.
     */
    public function isEditable(): bool
    {
        return $this->audit_status === self::DRAFT;
    }

    /**
     * @param  array<int, string>|null  $changing  null means a content change outside the header
     *
     * @throws ApiException
     */
    public function ensureEditable(?array $changing = null): void
    {
        if ($this->isEditable()) {
            return;
        }

        if ($changing !== null && array_diff($changing, self::FIELDS_ALLOWED_WHEN_LOCKED) === []) {
            return;
        }

        throw new ApiException('BOM 已提交审核，不可修改，请新建版本', 403, status: 403);
    }

    /**
     * Keep the denormalised product id in step with the version, so the
     * product-wide version unique index can never be bypassed.
     */
    protected static function booted(): void
    {
        static::creating(function (Bom $bom): void {
            $bom->product_id = ProductVersion::query()
                ->whereKey($bom->product_version_id)
                ->firstOrFail()
                ->product_id;
        });
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * @return BelongsTo<ProductVersion, $this>
     */
    public function productVersion(): BelongsTo
    {
        return $this->belongsTo(ProductVersion::class, 'product_version_id');
    }

    /**
     * @return HasMany<BomItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(BomItem::class, 'bom_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audited_at' => 'datetime',
            'status' => 'boolean',
        ];
    }
}
