<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class LabPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'lab.bill.create',
            'lab.bill.discount',
            'lab.bill.cancel',
            'lab.specimen.collect',
            'lab.result.enter',
            'lab.result.validate',
            'lab.result.verify',
            'lab.report.amend',
            'lab.master.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Roles
        $superAdmin = Role::findOrCreate('Super Admin', 'web');
        $superAdmin->syncPermissions($permissions);

        $pathologist = Role::findOrCreate('Pathologist', 'web');
        $pathologist->syncPermissions([
            'lab.specimen.collect',
            'lab.result.enter',
            'lab.result.validate',
            'lab.result.verify',
            'lab.report.amend',
        ]);

        $technician = Role::findOrCreate('Lab Technician', 'web');
        $technician->syncPermissions([
            'lab.specimen.collect',
            'lab.result.enter',
            'lab.result.validate',
        ]);

        $phlebotomist = Role::findOrCreate('Phlebotomist', 'web');
        $phlebotomist->syncPermissions([
            'lab.specimen.collect',
        ]);

        $billingClerk = Role::findOrCreate('Lab Billing Clerk', 'web');
        $billingClerk->syncPermissions([
            'lab.bill.create',
            'lab.bill.discount',
            'lab.bill.cancel',
        ]);

        // Ensure default test/admin user exists and has Super Admin role
        $adminUser = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'System Administrator',
                'email' => 'admin@hospital.local',
                'password' => Hash::make('admin123'),
                'active' => 1,
            ]
        );
        $adminUser->assignRole($superAdmin);

        // Ensure default pathologist exists
        $pathologistUser = User::firstOrCreate(
            ['username' => 'pathologist'],
            [
                'name' => 'Dr. Jane Pathologist (MD)',
                'email' => 'pathologist@hospital.local',
                'password' => Hash::make('patho123'),
                'active' => 1,
                'is_doctor' => 1,
            ]
        );
        $pathologistUser->assignRole($pathologist);

        // Ensure default technician exists
        $techUser = User::firstOrCreate(
            ['username' => 'technician'],
            [
                'name' => 'John Technician',
                'email' => 'technician@hospital.local',
                'password' => Hash::make('tech123'),
                'active' => 1,
            ]
        );
        $techUser->assignRole($technician);
    }
}
