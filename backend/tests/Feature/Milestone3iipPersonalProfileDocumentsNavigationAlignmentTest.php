<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3iipPersonalProfileDocumentsNavigationAlignmentTest extends TestCase
{
    public function test_document_records_the_personal_read_only_source_truth_boundary(): void
    {
        $document = file_get_contents(base_path('docs/milestone_3iip_personal_profile_documents_navigation_alignment.md'));

        $this->assertIsString($document);
        foreach ([
            'c6d13a14ca870d8396584f395fa22bda185f0527',
            'My Profile Documents',
            'GET /clubs/{club}/members/me',
            'GET /api/v1/clubs/{club}/members/me',
            'GET /clubs/{club}/members/{member}/profile-documents',
            'GET /api/v1/clubs/{club}/members/{member}/profile-documents',
            'File content, upload, download and preview are not available through this screen.',
            'escaped non-clickable text',
            'AbortController',
            'request-generation token',
            'uses GET requests only',
            'SQLite `:memory:` only',
        ] as $required) {
            $this->assertStringContainsString($required, $document);
        }

        $this->assertStringNotContainsString('no file content is stored anywhere', strtolower($document));
    }
}
