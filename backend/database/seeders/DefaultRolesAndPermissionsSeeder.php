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
                'name' => 'Issue Member Account Activations',
                'slug' => 'members.account_activation.issue',
                'group' => 'members',
                'description' => 'Issue one-time account activation links for eligible linked members.',
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
            ],
            [
                'name' => 'View Announcements',
                'slug' => 'announcements.view',
                'group' => 'announcements',
                'description' => 'View authorized same-club announcement records.',
            ],
            [
                'name' => 'Management Read Announcements',
                'slug' => 'announcements.management.view',
                'group' => 'announcements',
                'description' => 'Read same-club announcement management records without mutation authority.',
            ],
            [
                'name' => 'Manage Announcement Drafts',
                'slug' => 'announcements.drafts.manage',
                'group' => 'announcements',
                'description' => 'Create and edit authorized same-club announcement drafts.',
            ],
            [
                'name' => 'Create Announcement Replacements',
                'slug' => 'announcements.replace',
                'group' => 'announcements',
                'description' => 'Create authorized same-club correction or replacement drafts.',
            ],
            [
                'name' => 'Review Announcement Publication',
                'slug' => 'announcements.approve',
                'group' => 'announcements',
                'description' => 'Approve, reject or revoke publication approval for authorized announcement versions.',
            ],
            [
                'name' => 'Publish Announcements',
                'slug' => 'announcements.publish',
                'group' => 'announcements',
                'description' => 'Explicitly publish authorized same-club announcement drafts.',
            ],
            [
                'name' => 'Withdraw Announcements',
                'slug' => 'announcements.withdraw',
                'group' => 'announcements',
                'description' => 'Withdraw authorized same-club published announcements.',
            ],
            [
                'name' => 'Archive Announcements',
                'slug' => 'announcements.archive',
                'group' => 'announcements',
                'description' => 'Archive authorized same-club announcements.',
            ],
            [
                'name' => 'View Reports',
                'slug' => 'reports.view',
                'group' => 'reports',
                'description' => 'View authorized club reports.',
            ],
            ['name' => 'View Own Finance', 'slug' => 'finance.self.view', 'group' => 'finance', 'description' => 'View own obligations, payments, receipts and balance.'],
            ['name' => 'View Club Finance', 'slug' => 'finance.club.view', 'group' => 'finance', 'description' => 'View club member collection records.'],
            ['name' => 'View Club Account Statements', 'slug' => 'finance.club.statement.view', 'group' => 'finance', 'description' => 'View same-club cash and bank account statements.'],
            ['name' => 'Manage Club Finance', 'slug' => 'finance.manage', 'group' => 'finance', 'description' => 'Create charges and record allocated member payments.'],
            ['name' => 'Void Club Payments', 'slug' => 'finance.void', 'group' => 'finance', 'description' => 'Void incorrect member payments.'],
            ['name' => 'Configure Accounting Integration', 'slug' => 'finance.accounting.integration.configure', 'group' => 'finance_accounting', 'description' => 'Propose or independently decide protected accounting integration configuration.'],
            ['name' => 'Prepare Cutover Package', 'slug' => 'finance.cutover.package.prepare', 'group' => 'finance_cutover', 'description' => 'Prepare one explicitly authorized cutover package.'],
            ['name' => 'Validate Cutover Package', 'slug' => 'finance.cutover.package.validate', 'group' => 'finance_cutover', 'description' => 'Validate one explicitly authorized cutover package.'],
            ['name' => 'Verify Cutover Package', 'slug' => 'finance.cutover.package.verify', 'group' => 'finance_cutover', 'description' => 'Internally verify one explicitly authorized cutover package.'],
            ['name' => 'Review Cutover Accounting', 'slug' => 'finance.cutover.package.accounting-review', 'group' => 'finance_cutover', 'description' => 'Record an appointed independent accounting review.'],
            ['name' => 'Approve Cutover Business Decision', 'slug' => 'finance.cutover.package.business-approve', 'group' => 'finance_cutover', 'description' => 'Record the authenticated President cutover approval.'],
            ['name' => 'Accept Cutover Dry Run', 'slug' => 'finance.cutover.package.dry-run-accept', 'group' => 'finance_cutover', 'description' => 'Accept an exact package-bound cutover dry run.'],
            ['name' => 'Authorize Cutover Activation', 'slug' => 'finance.cutover.package.activate-authorize', 'group' => 'finance_cutover', 'description' => 'Authorize exact one-time cutover activation.'],
            ['name' => 'Execute Cutover Activation', 'slug' => 'finance.cutover.package.execute', 'group' => 'finance_cutover', 'description' => 'Execute one appointed package-bound cutover activation.'],
            ['name' => 'Authorize Cutover Rollback', 'slug' => 'finance.cutover.package.rollback-authorize', 'group' => 'finance_cutover', 'description' => 'Authorize a package-bound revocation or rollback decision.'],
            ['name' => 'View Restricted Cutover Manifest', 'slug' => 'finance.cutover.package.view-restricted-manifest', 'group' => 'finance_cutover', 'description' => 'View the least-privilege manifest for an appointed cutover package.'],
            ['name' => 'Submit Own Profile Updates', 'slug' => 'profile_updates.self.submit', 'group' => 'profile_updates', 'description' => 'Submit and withdraw own profile update requests.'],
            ['name' => 'View Own Profile Updates', 'slug' => 'profile_updates.self.view', 'group' => 'profile_updates', 'description' => 'View own profile update request history.'],
            ['name' => 'Review Profile Updates', 'slug' => 'profile_updates.review', 'group' => 'profile_updates', 'description' => 'View the same-club profile update review queue.'],
            ['name' => 'Approve Profile Updates', 'slug' => 'profile_updates.approve', 'group' => 'profile_updates', 'description' => 'Approve or reject same-club profile update requests.'],
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
                    'members.account_activation.issue',
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
                    'announcements.view',
                    'announcements.management.view',
                    'announcements.drafts.manage',
                    'announcements.replace',
                    'announcements.approve',
                    'announcements.publish',
                    'announcements.withdraw',
                    'announcements.archive',
                    'reports.view',
                    'finance.self.view',
                    'finance.club.view',
                    'finance.club.statement.view',
                    'finance.manage',
                    'finance.void',
                    'profile_updates.self.submit',
                    'profile_updates.self.view',
                    'profile_updates.review',
                    'profile_updates.approve',
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
                    'members.account_activation.issue',
                    'audit_logs.view',
                    'events.view',
                    'events.manage',
                    'projects.view',
                    'projects.manage',
                    'reminders.view',
                    'reminders.manage',
                    'announcements.view',
                    'announcements.management.view',
                    'announcements.drafts.manage',
                    'announcements.replace',
                    'announcements.approve',
                    'announcements.publish',
                    'announcements.withdraw',
                    'announcements.archive',
                    'reports.view',
                    'finance.self.view',
                    'finance.club.view',
                    'finance.club.statement.view',
                    'finance.manage',
                    'profile_updates.self.submit',
                    'profile_updates.self.view',
                    'profile_updates.review',
                    'profile_updates.approve',
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
                    'announcements.view',
                    'reports.view',
                    'finance.self.view',
                    'finance.club.view',
                    'finance.club.statement.view',
                    'finance.manage',
                    'finance.void',
                    'profile_updates.self.submit',
                    'profile_updates.self.view',
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
                    'announcements.view',
                    'finance.self.view',
                    'profile_updates.self.submit',
                    'profile_updates.self.view',
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
