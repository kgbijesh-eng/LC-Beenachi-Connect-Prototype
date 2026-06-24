<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3glReportsFrontendIntegrationTest extends TestCase
{
    public function test_frontend_uses_only_backend_report_output(): void
    {
        $component=file_get_contents(dirname(__DIR__,3).'/app/ReportsBackend.tsx');
        $page=file_get_contents(dirname(__DIR__,3).'/app/page.tsx');
        $doc=file_get_contents(__DIR__.'/../../docs/milestone_3gl_reports_frontend_integration.md');
        foreach ([
            '/reports/${def.key}', 'clearOutput()', 'setReport(null);setMeta(null)',
            'Backend-calculated', 'no exports', 'generated_at', 'club_timezone',
            'Previous report output was cleared', 'No genuine data',
            '3 members (2 active, 1 inactive)', 'Normal Member has no Reports menu',
        ] as $expected) $this->assertStringContainsString($expected,$component.$doc);
        $this->assertStringContainsString('import ReportsBackend from "./ReportsBackend";',$page);
        $this->assertStringContainsString('<ReportsBackend role={role}',$page);
        $this->assertStringNotContainsString('collections-summary',$component);
        $this->assertStringNotContainsString('localStorage',$component);
    }
}
