<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3ggaRemindersAuthorizationPermissionRegressionRepairTest extends TestCase
{
    public function test_repair_is_exact_and_runtime_free(): void
    {
        $auth = file_get_contents(__DIR__.'/AuthMeRoleAwareResponseTest.php');
        $service = file_get_contents(__DIR__.'/ClubPermissionServiceTest.php');
        $doc = file_get_contents(__DIR__.'/../../docs/milestone_3gga_reminders_authorization_permission_regression_repair.md');

        foreach (['projects.manage', 'projects.view', 'reminders.manage', 'reminders.view'] as $permission) {
            $this->assertStringContainsString("'{$permission}'", $auth);
            $this->assertStringContainsString("'{$permission}'", $service);
        }

        $this->assertStringContainsString('assertSame([', $auth);
        $this->assertStringContainsString('assertSame([', $service);
        $this->assertStringContainsString('stale exact test expectations', $doc);
        $this->assertStringContainsString('No runtime assignment or authorization logic was changed.', $doc);
        $this->assertStringContainsString('Retry 3GG', $doc);
    }
}
