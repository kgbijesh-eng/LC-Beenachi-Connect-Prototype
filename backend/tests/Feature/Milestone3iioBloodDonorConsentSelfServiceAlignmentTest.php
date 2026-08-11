<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3iioBloodDonorConsentSelfServiceAlignmentTest extends TestCase
{
    public function test_milestone_document_records_the_narrow_identity_api_and_review_boundary(): void
    {
        $document = file_get_contents(base_path('docs/milestone_3iio_blood_donor_consent_self_service_alignment.md'));

        $this->assertIsString($document);
        foreach ([
            '9a0cf9f18e5096c24ed16a4c25533e748739ae79',
            'GET /clubs/{club}/members/me',
            'POST /clubs/{club}/profile-update-requests',
            'POST /api/v1/clubs/{club}/profile-update-requests',
            'President or Secretary',
            'No `member_id`, `user_id`, or caller-controlled `club_id` is submitted.',
            'Boolean `false` is preserved as an intentional changed value.',
            'Current authoritative values remain displayed after successful submission.',
            'It adds no donor directory',
        ] as $required) {
            $this->assertStringContainsString($required, $document);
        }

        $this->assertStringNotContainsString('President/Admin', $document);
    }
}
