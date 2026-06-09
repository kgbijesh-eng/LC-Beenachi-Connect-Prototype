<?php
declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3aePaymentReceiptPdfValidationAndClosureTest extends TestCase
{
    public function test_milestone_3ae_closure_document_exists_and_records_validation_decision(): void
    {
        $path = base_path('docs/milestone_3ae_payment_receipt_pdf_validation_and_closure.md');

        $this->assertFileExists($path);

        $contents = File::get($path);

        $this->assertStringContainsString('Milestone 3AE — Payment Receipt PDF Validation and Closure', $contents);
        $this->assertStringContainsString('PAYMENT_RECEIPT_PDF_VALIDATED_AND_CLOSED', $contents);
        $this->assertStringContainsString('docs/tests-only', $contents);
        $this->assertStringContainsString('3AD PDF route exists', $contents);
        $this->assertStringContainsString('PDF package is installed', $contents);
        $this->assertStringContainsString('Authorized users can download payment receipt PDFs', $contents);
        $this->assertStringContainsString('Normal members can download only their own receipt PDF', $contents);
        $this->assertStringContainsString('Cross-club access fails closed', $contents);
        $this->assertStringContainsString('Unauthenticated access fails closed', $contents);
        $this->assertStringContainsString('PDF route does not create a payment', $contents);
        $this->assertStringContainsString('PDF route does not create a ledger entry', $contents);
        $this->assertStringContainsString('PDF route does not create a duplicate receipt', $contents);
    }

    public function test_receipt_pdf_package_controller_service_and_view_are_present(): void
    {
        $composerJson = File::get(base_path('composer.json'));

        $this->assertStringContainsString('barryvdh/laravel-dompdf', $composerJson);

        $this->assertNotNull(
            $this->findFileByName(base_path('app'), 'PaymentReceiptPdfController.php'),
            'Expected PaymentReceiptPdfController.php to remain present from 3AD.'
        );

        $this->assertNotNull(
            $this->findFileByName(base_path('app'), 'PaymentReceiptPdfService.php'),
            'Expected PaymentReceiptPdfService.php to remain present from 3AD.'
        );

        $this->assertFileExists(
            base_path('resources/views/pdf/receipt.blade.php'),
            'Expected receipt PDF Blade view to remain present from 3AD.'
        );
    }

    public function test_receipt_pdf_route_exists_as_protected_get_route(): void
    {
        $routes = collect(Route::getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'payments/{payment}/receipt/pdf'))
            ->values();

        $this->assertCount(1, $routes, 'Expected exactly one payment receipt PDF route.');

        $route = $routes->first();

        $this->assertContains('GET', $route->methods());
        $this->assertNotContains('POST', $route->methods());
        $this->assertNotContains('PATCH', $route->methods());
        $this->assertNotContains('PUT', $route->methods());
        $this->assertNotContains('DELETE', $route->methods());

        $this->assertStringContainsString('PaymentReceiptPdfController', $route->getActionName());

        $middleware = implode(' ', $route->gatherMiddleware());

        $this->assertStringContainsString('auth:sanctum', $middleware);
    }

    public function test_receipt_pdf_implementation_does_not_add_mutation_or_sharing_scope(): void
    {
        $controller = $this->findFileByName(base_path('app'), 'PaymentReceiptPdfController.php');
        $service = $this->findFileByName(base_path('app'), 'PaymentReceiptPdfService.php');

        $this->assertNotNull($controller);
        $this->assertNotNull($service);

        $contents = File::get($controller) . "\n" . File::get($service);

        $forbiddenFragments = [
            'Payment::create',
            'PaymentReceipt::create',
            'MemberLedgerEntry::create',
            'LedgerEntry::create',
            '->delete(',
            'Storage::put',
            'Storage::disk',
            'temporarySignedRoute',
            'signedRoute',
            'Mail::',
            'Notification::send',
            'Excel::',
            'WhatsApp',
            'SMS',
        ];

        foreach ($forbiddenFragments as $fragment) {
            $this->assertStringNotContainsString(
                $fragment,
                $contents,
                "3AE boundary violation: PDF receipt implementation must not contain {$fragment}."
            );
        }
    }

    public function test_milestone_3ae_keeps_frontend_storage_sharing_reports_and_excel_out_of_scope(): void
    {
        $document = base_path('docs/milestone_3ae_payment_receipt_pdf_validation_and_closure.md');

        $contents = File::get($document);

        $outOfScopeItems = [
            'frontend receipt PDF buttons',
            'stored PDFs',
            'public receipt URLs',
            'signed receipt URLs',
            'WhatsApp sharing',
            'SMS sharing',
            'email sending',
            'finance reports',
            'Excel import/export',
            'payment cancellation',
            'payment reversal',
            'receipt correction',
            'receipt reissue',
        ];

        foreach ($outOfScopeItems as $scope) {
            $this->assertStringContainsString($scope, $contents);
        }
    }

    private function findFileByName(string $directory, string $filename): ?string
    {
        if (! is_dir($directory)) {
            return null;
        }

        foreach (File::allFiles($directory) as $file) {
            if ($file->getFilename() === $filename) {
                return $file->getPathname();
            }
        }

        return null;
    }
}
