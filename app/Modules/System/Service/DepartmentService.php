<?php

namespace App\Modules\System\Service;

use App\Core\Exceptions\ApiException;
use App\Modules\Organization\Model\Employee;
use App\Modules\System\Model\Department;
use App\Modules\System\ValueObject\OperationLogEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DepartmentService
{
    public function __construct(private readonly OperationLogService $operationLogs) {}

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
        return DB::transaction(function () use ($data): Department {
            $department = Department::query()->create($data);

            $this->operationLogs->record(new OperationLogEntry(
                action: 'system.department.create',
                actionName: '新增部门',
                objectType: '部门',
                objectId: $department->id,
                objectName: $department->name,
            ));

            return $department;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Department $department, array $data): Department
    {
        return DB::transaction(function () use ($department, $data): Department {
            $before = $this->snapshot($department);
            $department->update($data);

            $this->operationLogs->record(new OperationLogEntry(
                action: 'system.department.update',
                actionName: '编辑部门',
                objectType: '部门',
                objectId: $department->id,
                objectName: $department->name,
                before: $before,
                after: $this->snapshot($department),
            ));

            return $department;
        });
    }

    public function delete(Department $department): void
    {
        if ($department->children()->exists()) {
            throw new ApiException('存在下级部门，不能删除', 403, status: 403);
        }

        if ($department->users()->exists()) {
            throw new ApiException('部门下还有用户，不能删除', 403, status: 403);
        }

        // Employees carry a department and the foreign key nulls it out, so a
        // delete here would silently strip their org assignment.
        if (Employee::query()->where('department_id', $department->id)->exists()) {
            throw new ApiException('部门下还有员工，不能删除', 403, status: 403);
        }

        DB::transaction(function () use ($department): void {
            $name = $department->name;
            $department->delete();

            $this->operationLogs->record(new OperationLogEntry(
                action: 'system.department.delete',
                actionName: '删除部门',
                objectType: '部门',
                objectId: $department->id,
                objectName: $name,
            ));
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Department $department): array
    {
        return [
            '名称' => $department->name,
            '编码' => $department->code,
            '上级部门' => $department->parent_id === null
                ? null
                : Department::query()->whereKey($department->parent_id)->value('name'),
            '排序' => $department->sort,
        ];
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
