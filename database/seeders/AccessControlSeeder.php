<?php

namespace Database\Seeders;

/**
 * Seeds menus, permissions and roles without demo users or departments.
 */
class AccessControlSeeder extends SystemSeeder
{
    protected function seedsDemoData(): bool
    {
        return false;
    }
}
