<?php

namespace App\Modules\System\Service;

use App\Core\Exceptions\ApiException;
use App\Modules\System\Model\User;
use Illuminate\Pagination\LengthAwarePaginator;

class UserService
{
    public function __construct(private readonly AccessControlService $access) {}

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(?string $keyword = null, ?bool $status = null): LengthAwarePaginator
    {
        return User::query()
            ->with(['roles', 'department'])
            ->when($keyword, function ($query, string $keyword): void {
                $query->where(fn ($inner) => $inner
                    ->where('name', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%")
                    ->orWhere('phone', 'like', "%{$keyword}%"));
            })
            ->when($status !== null, function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->orderBy('id')
            ->paginate(15);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $roleIds
     */
    public function create(User $actor, array $data, array $roleIds): User
    {
        $this->access->ensureCanGrantRoles($actor, $roleIds);

        $user = User::query()->create($data);
        $user->roles()->sync($roleIds);

        return $user->load('roles');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, User $user, array $data): User
    {
        $this->access->ensureCanManageUser($actor, $user);

        if (array_key_exists('status', $data) && $data['status'] === false && $user->is($actor)) {
            throw new ApiException('不能停用当前登录账号', 403, status: 403);
        }

        $user->update($data);

        return $user->load('roles');
    }

    public function resetPassword(User $actor, User $user, string $password): void
    {
        $this->access->ensureCanManageUser($actor, $user);

        $user->update(['password' => $password]);
        $user->tokens()->delete();
    }

    /**
     * @param  array<int, int>  $roleIds
     */
    public function assignRoles(User $actor, User $user, array $roleIds): User
    {
        $this->access->ensureCanManageUser($actor, $user);
        $this->access->ensureCanGrantRoles($actor, $roleIds);

        $user->roles()->sync($roleIds);

        return $user->load('roles');
    }
}
