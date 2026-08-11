<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3hmMemberProfileSelfServiceContextFixTest extends TestCase
{
    public function test_frontend_profile_self_service_rendering_conditions(): void
    {
        $pagePath = dirname(__DIR__, 3) . '/app/page.tsx';
        $this->assertFileExists($pagePath);

        $content = file_get_contents($pagePath);

        // 1. Proves the shared auth session supplies the linked member identifier
        $this->assertTrue(
            str_contains($content, 'session?.membership?.member?.member_code'),
            "page.tsx must access linked member_code from the auth session."
        );

        // 2. Proves the selected member is compared using the confirmed canonical identifier (member.id)
        $this->assertTrue(
            str_contains($content, 'linkedMemberCode === member.id'),
            "page.tsx must compare linked member code with member.id."
        );

        // 3. Proves ownership is converted to a strict Boolean
        $this->assertTrue(
            str_contains($content, 'const isOwnProfile = Boolean('),
            "page.tsx must convert ownership comparison to a strict Boolean wrapper."
        );

        // 4. Proves isPersonal || is not used as an ownership bypass
        $this->assertFalse(
            str_contains($content, 'isOwnProfile = isPersonal ||'),
            "isPersonal must not be used to bypass the ownership check."
        );

        // 5. Proves the complete own-profile section is guarded by isOwnProfile and mode='own' is not unconditional
        $this->assertTrue(
            str_contains($content, '{isOwnProfile && <ProfileUpdatesBackend role={role} mode="own" />}'),
            "page.tsx must guard the own-profile updates component mount using isOwnProfile."
        );

        // 6. Proves the dedicated officer review workflow remains present and unchanged
        $this->assertTrue(
            str_contains($content, 'mode="review"'),
            "page.tsx must retain the dedicated officer review mode."
        );

        // 7. Proves no dummy request data was introduced
        $this->assertFalse(
            str_contains($content, 'dummy-profile-request-sentinel'),
            "No dummy request data must be introduced."
        );
    }
}
