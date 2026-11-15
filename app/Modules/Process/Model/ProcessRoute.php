<?php

namespace App\Modules\Process\Model;

use App\Core\Exceptions\ApiException;
use App\Modules\Product\Model\Product;
use Database\Factories\ProcessRouteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property int $product_id
 * @property string $version
 * @property string $audit_status
 * @property Carbon|null $audited_at
 * @property string|null $remark
 * @property bool $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'product_id', 'version', 'audit_status', 'audited_at', 'remark', 'status'])]
#[Table('mes_routes')]
#[UseFactory(ProcessRouteFactory::class)]
class ProcessRoute extends Model
{
    /** @use HasFactory<ProcessRouteFactory> */
    use HasFactory;

    public const string DRAFT = 'draft';

    public const string PENDING = 'pending';

    public const string APPROVED = 'approved';

    /**
     * A lock only stops content changes; a retired route must still be disable-able.
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
     * Only a draft may still change; submitting locks the route.
     */
    public function isEditable(): bool
    {
        return $this->audit_status === self::DRAFT;
    }

    /**
     * What an order may pin: approved, enabled, on an enabled product, with no
     * retired step. Kept on the model so order validation and dropdowns agree.
     *
     * @param  Builder<self>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->where('audit_status', self::APPROVED)
            ->where('status', true)
            ->whereHas('product', fn (Builder $inner) => $inner->where('status', true))
            ->whereDoesntHave('operations.process', fn (Builder $inner) => $inner->where('status', false));
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

        throw new ApiException('工艺路线已提交审核，不可修改，请新建版本', 403, status: 403);
    }

    /**
     * A route joins the audit flow only while its product and every process
     * stay active; otherwise production would inherit a step nobody can run.
     *
     * @throws ApiException
     */
    public function ensureAuditable(string $action): void
    {
        if (! $this->status) {
            throw new ApiException("工艺路线已停用，不能{$action}", 422);
        }

        $this->loadMissing('product');

        if (! $this->product->status) {
            throw new ApiException("产品已停用，不能{$action}", 422);
        }

        if ($this->operations()->whereHas('process', fn ($query) => $query->where('status', false))->exists()) {
            throw new ApiException("工艺路线包含已停用的工序，不能{$action}", 422);
        }
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * @return HasMany<RouteOperation, $this>
     */
    public function operations(): HasMany
    {
        return $this->hasMany(RouteOperation::class, 'route_id')->orderBy('sequence');
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
