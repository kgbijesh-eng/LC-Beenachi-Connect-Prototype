<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class DefaultRolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'View Club Foundation',
                'slug' => 'club.view',
                'group' => 'club',
                'description' => 'View basic club foundation details.',
            ],
            [
                'name' => 'Manage Club Foundation',
                'slug' => 'club.manage',
                'group' => 'club',
                'description' => 'Manage basic club foundation details.',
            ],
            [
                'name' => 'View Users',
                'slug' => 'users.view',
                'group' => 'users',
                'description' => 'View registered user accounts.',
            ],
            [
                'name' => 'Manage Users',
                'slug' => 'users.manage',
                'group' => 'users',
                'description' => 'Manage registered user accounts.',
            ],
            [
                'name' => 'View Club Memberships',
                'slug' => 'memberships.view',
                'group' => 'memberships',
                'description' => 'View user membership links within clubs.',
            ],
            [
                'name' => 'Manage Club Memberships',
                'slug' => 'memberships.manage',
                'group' => 'memberships',
                'description' => 'Manage user membership links within clubs.',
            ],
            [
                'name' => 'View Members',
                'slug' => 'members.view',
                'group' => 'members',
                'description' => 'View member master records.',
            ],
            [
                'name' => 'Manage Members',
                'slug' => 'members.manage',
                'group' => 'members',
                'description' => 'Create and update member master records.',
            ],
            [
                'name' => 'View Roles',
                'slug' => 'roles.view',
                'group' => 'roles',
                'description' => 'View role definitions.',
            ],
            [
                'name' => 'Manage Roles',
                'slug' => 'roles.manage',
                'group' => 'roles',
                'description' => 'Manage role definitions.',
            ],
            [
                'name' => 'View Permissions',
                'slug' => 'permissions.view',
                'group' => 'permissions',
                'description' => 'View permission definitions.',
            ],
            [
                'name' => 'View Audit Logs',
                'slug' => 'audit_logs.view',
                'group' => 'audit_logs',
                'description' => 'View audit log records.',
            ],
            [
                'name' => 'View Events and Meetings',
                'slug' => 'events.view',
                'group' => 'events',
                'description' => 'View authorized Events and Meetings records.',
            ],
            [
                'name' => 'Manage Events and Meetings',
                'slug' => 'events.manage',
                'group' => 'events',
                'description' => 'Create, update and transition Events and Meetings records.',
            ],
            [
                'name' => 'View Projects and Activities',
                'slug' => 'projects.view',
                'group' => 'projects',
                'description' => 'View authorized Projects and Activities records.',
            ],
            [
                'name' => 'Manage Projects and Activities',
                'slug' => 'projects.manage',
                'group' => 'projects',
                'description' => 'Create, update and transition Projects and Activities records.',
            ],            [
                'name' => 'View Reminders',
                'slug' => 'reminders.view',
                'group' => 'reminders',
                'description' => 'View authorized club reminder records.',
            ],
            [
                'name' => 'Manage Reminders',
                'slug' => 'reminders.manage',
                'group' => 'reminders',
                'description' => 'Create, update and transition club reminder records.',
            ],            [
                'name' => 'View Reports',
                'slug' => 'reports.view',
                'group' => 'reports',
                'description' => 'View authorized club reports.',
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['slug' => $permission['slug']],
                $permission
            );
        }

        $roles = [
            'president-admin' => [
                'name' => 'President/Admin',
                'description' => 'Primary club administrator with full foundation access.',
                'permissions' => [
                    'club.view',
                    'club.manage',
                    'users.view',
                    'users.manage',
                    'memberships.view',
                    'memberships.manage',
                    'members.view',
                    'members.manage',
                    'roles.view',
                    'roles.manage',
                    'permissions.view',
                    'audit_logs.view',
                    'events.view',
                    'events.manage',
                    'projects.view',
                    'projects.manage',
                    'reminders.view',
                    'reminders.manage',
                    'reports.view',
                ],
            ],
            'secretary' => [
                'name' => 'Secretary',
                'description' => 'Club secretary with operational foundation visibility.',
                'permissions' => [
                    'club.view',
                    'users.view',
                    'memberships.view',
                    'members.view',
                    'members.manage',
                    'audit_logs.view',
                    'events.view',
                    'events.manage',
                    'projects.view',
                    'projects.manage',
                    'reminders.view',
                    'reminders.manage',
                    'reports.view',
                ],
            ],
            'treasurer' => [
                'name' => 'Treasurer',
                'description' => 'Club treasurer with controlled foundation visibility.',
                'permissions' => [
                    'club.view',
                    'users.view',
                    'memberships.view',
                    'members.view',
                    'audit_logs.view',
                    'events.view',
                    'projects.view',
                    'reminders.view',
                    'reports.view',
                ],
            ],
            'member' => [
                'name' => 'Member',
                'description' => 'Normal club member with limited foundation visibility.',
                'permissions' => [
                    'club.view',
                    'members.view',
                    'events.view',
                    'projects.view',
                    'reminders.view',
                ],
            ],
        ];

        foreach ($roles as $slug => $roleData) {
            $role = Role::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $roleData['name'],
                    'description' => $roleData['description'],
                    'is_system' => true,
                ]
            );

            $permissionIds = Permission::query()
                ->whereIn('slug', $roleData['permissions'])
                ->pluck('id')
                ->all();

            $role->permissions()->sync($permissionIds);
        }
    }
}
