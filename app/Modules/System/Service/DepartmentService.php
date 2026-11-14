<?php

namespace App\Modules\System\Service;

use App\Core\Exceptions\ApiException;
use App\Modules\System\Model\Department;
use Illuminate\Support\Collection;

class DepartmentService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function tree(): array
    {
        return $this->nest(Department::query()->orderBy('sort')->orderBy('id')->get(), null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Department
    {
        return Department::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Department $department, array $data): Department
    {
        $department->update($data);

        return $department;
    }

    public function delete(Department $department): void
    {
        if ($department->children()->exists()) {
            throw new ApiException('存在下级部门，不能删除', 403, status: 403);
        }

        if ($department->users()->exists()) {
            throw new ApiException('部门下还有用户，不能删除', 403, status: 403);
        }

        $department->delete();
    }

    /**
     * @param  Collection<int, Department>  $departments
     * @return array<int, array<string, mixed>>
     */
    private function nest(Collection $departments, ?int $parentId): array
    {
        return $departments
            ->where('parent_id', $parentId)
            ->map(fn (Department $department) => [
                'id' => $department->id,
                'parent_id' => $department->parent_id,
                'code' => $department->code,
                'name' => $department->name,
                'sort' => $department->sort,
                'children' => $this->nest($departments, $department->id),
            ])
            ->values()
            ->all();
    }
}
