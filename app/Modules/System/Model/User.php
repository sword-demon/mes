<?php

namespace App\Modules\System\Model;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Modules\Organization\Model\Employee;
use App\Modules\Organization\Model\Factory as OrganizationFactory;
use App\Modules\Organization\Model\ProductionLine;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string|null $username
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property int|null $department_id
 * @property int|null $factory_id
 * @property int|null $employee_id
 * @property string|null $position
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $status
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['username', 'name', 'email', 'phone', 'department_id', 'factory_id', 'employee_id', 'position', 'password', 'status'])]
#[Hidden(['password', 'remember_token'])]
#[UseFactory(UserFactory::class)]
#[Table('sys_users')]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Mirrors the column default so freshly built models expose it.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => true,
    ];

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'sys_user_roles', 'user_id', 'role_id');
    }

    /**
     * Roles that currently grant access; a disabled role grants nothing.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function activeRoles(): BelongsToMany
    {
        return $this->roles()->where('status', true);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * The plant this account belongs to.
     *
     * Named `organizationFactory` because `factory()` is taken by the
     * `HasFactory` trait.
     *
     * @return BelongsTo<OrganizationFactory, $this>
     */
    public function organizationFactory(): BelongsTo
    {
        return $this->belongsTo(OrganizationFactory::class, 'factory_id');
    }

    /**
     * Production lines this account is responsible for.
     *
     * @return BelongsToMany<ProductionLine, $this>
     */
    public function productionLines(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductionLine::class,
            'sys_user_production_lines',
            'user_id',
            'production_line_id',
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'boolean',
        ];
    }
}
