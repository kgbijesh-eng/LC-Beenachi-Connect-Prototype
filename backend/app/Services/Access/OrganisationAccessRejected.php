<?php

declare(strict_types=1);

namespace App\Services\Access;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

final class OrganisationAccessRejected extends RuntimeException
{
    private function __construct(
        public readonly string $reason,
        public readonly int $status,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function unavailable(?Throwable $previous = null): self
    {
        return new self('ORGANISATION_CONFIGURATION_UNAVAILABLE', 503, 'Organisation configuration is unavailable.', $previous);
    }

    public static function foreign(): self
    {
        return new self('ORGANISATION_FOREIGN', 404, 'Not found.');
    }

    public static function suspended(): self
    {
        return new self('ORGANISATION_SUSPENDED', 403, 'Organisation access is suspended.');
    }

    public static function unsupported(): self
    {
        return new self('ORGANISATION_TRANSACTION_UNSUPPORTED', 409, 'A supported outer organisation transaction is required.');
    }

    public static function invalidContext(?Throwable $previous = null): self
    {
        return new self('ORGANISATION_TRANSACTION_CONTEXT_INVALID', 409, 'Organisation transaction context is no longer valid.', $previous);
    }

    public function render(Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        $body = ['message' => $this->getMessage()];
        if ($this->status !== 404 && $this->status !== 503) {
            $body['code'] = $this->reason;
        }

        return response()->json($body, $this->status);
    }
}
