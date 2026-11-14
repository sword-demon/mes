<?php

namespace App\Modules\System\Service;

use App\Modules\System\Model\User;
use Illuminate\Pagination\LengthAwarePaginator;

class UserService
{
    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(?string $keyword = null, ?bool $status = null): LengthAwarePaginator
    {
        return User::query()
            ->with('roles')
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
    public function create(array $data, array $roleIds): User
    {
        $user = User::query()->create($data);
        $user->roles()->sync($roleIds);

        return $user->load('roles');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user->load('roles');
    }

    public function resetPassword(User $user, string $password): void
    {
        $user->update(['password' => $password]);
        $user->tokens()->delete();
    }

    /**
     * @param  array<int, int>  $roleIds
     */
    public function assignRoles(User $user, array $roleIds): User
    {
        $user->roles()->sync($roleIds);

        return $user->load('roles');
    }
}
