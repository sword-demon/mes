<?php

namespace App\Modules\System\Model;

use Database\Factories\MenuFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property int|null $permission_id
 * @property string $name
 * @property string|null $path
 * @property string|null $icon
 * @property int $sort
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['parent_id', 'permission_id', 'name', 'path', 'icon', 'sort'])]
#[Table('sys_menus')]
#[UseFactory(MenuFactory::class)]
class Menu extends Model
{
    /** @use HasFactory<MenuFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Menu, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort')->orderBy('id');
    }

    /**
     * Nest a flat menu collection by `parent_id`, ordered by `sort`.
     *
     * @param  Collection<int, Menu>  $menus
     * @return array<int, array<string, mixed>>
     */
    public static function toTree(Collection $menus, ?int $parentId = null): array
    {
        return $menus
            ->where('parent_id', $parentId)
            ->map(fn (self $menu) => [
                'id' => $menu->id,
                'parent_id' => $menu->parent_id,
                'permission_id' => $menu->permission_id,
                'name' => $menu->name,
                'path' => $menu->path,
                'icon' => $menu->icon,
                'sort' => $menu->sort,
                'children' => self::toTree($menus, $menu->id),
            ])
            ->values()
            ->all();
    }
}
