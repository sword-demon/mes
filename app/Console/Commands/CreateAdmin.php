<?php

namespace App\Console\Commands;

use App\Modules\System\Model\Role;
use App\Modules\System\Model\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

#[Signature('mes:create-admin {--name=系统管理员 : 管理员姓名} {--email= : 管理员邮箱} {--password= : 初始密码；留空则自动生成}')]
#[Description('初始化 RBAC 元数据并创建超级管理员账号')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $email = trim((string) $this->option('email'));
        $name = trim((string) $this->option('name')) ?: '系统管理员';
        $password = $this->option('password');
        $password = is_string($password) && $password !== '' ? $password : null;

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            [
                'email' => ['required', 'string', 'email', 'max:255'],
                'password' => [
                    'nullable',
                    'string',
                    Password::min(12)->mixedCase()->letters()->numbers()->symbols(),
                ],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::INVALID;
        }

        if ($this->call('db:seed', [
            '--class' => AccessControlSeeder::class,
            '--force' => true,
        ]) !== self::SUCCESS) {
            $this->error('RBAC 元数据初始化失败');

            return self::FAILURE;
        }

        $generated = $password === null;
        $password ??= Str::password(20);

        $role = Role::query()->where('code', Role::SUPER_ADMIN)->firstOrFail();

        try {
            DB::transaction(function () use ($email, $name, $password, $role): void {
                $user = User::query()->create([
                    'username' => Str::before($email, '@'),
                    'name' => $name,
                    'email' => $email,
                    'position' => '系统管理员',
                    'password' => $password,
                    'status' => true,
                ]);

                $user->roles()->attach($role);
            });
        } catch (UniqueConstraintViolationException) {
            $this->error("用户 {$email} 已存在");

            return self::FAILURE;
        }

        $this->info("超级管理员已创建：{$email}");

        if ($generated) {
            $this->line("初始密码：{$password}");
            $this->warn('请登录后立即修改密码。');
        }

        return self::SUCCESS;
    }
}
