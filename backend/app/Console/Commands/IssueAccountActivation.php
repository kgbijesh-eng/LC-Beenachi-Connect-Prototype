<?php

namespace App\Console\Commands;

use App\Models\Club;
use App\Services\Access\OrganisationAccessRejected;
use App\Services\Auth\AccountActivationException;
use App\Services\Auth\AccountActivationService;
use Illuminate\Console\Command;

class IssueAccountActivation extends Command
{
    protected $signature = 'account:activation-issue
        {--club= : Exact club code}
        {--member= : Exact member code}
        {--email= : Intended operational email}';

    protected $description = 'Issue a one-time first-account activation link without accepting or setting a password';

    public function handle(AccountActivationService $service): int
    {
        if (! config('account_activation.bootstrap_enabled')) {
            $this->error('Bootstrap activation issuance is disabled.');

            return self::FAILURE;
        }

        $clubCode = trim((string) $this->option('club'));
        $memberCode = trim((string) $this->option('member'));
        $email = strtolower(trim((string) $this->option('email')));
        if ($clubCode === '' || $memberCode === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Exact club code, member code, and a valid operational email are required.');

            return self::INVALID;
        }

        $clubs = Club::query()->where('code', $clubCode)->get();
        if ($clubs->count() !== 1) {
            $this->error('The target club could not be resolved exactly.');

            return self::FAILURE;
        }

        $this->line("Target club: {$clubCode}");
        $this->line("Target member: {$memberCode}");
        $this->line("Operational email: {$email}");
        if (! $this->confirm('Issue a single-use activation for this exact target?')) {
            $this->warn('No activation was issued.');

            return self::FAILURE;
        }

        try {
            $issued = $service->issue($clubs->first(), $memberCode, $email, 'bootstrap', null);
        } catch (AccountActivationException|OrganisationAccessRejected $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->warn('Copy this activation link now. It will not be shown again:');
        $this->line($issued['link']);
        $this->warn('Disable bootstrap activation issuance immediately.');

        return self::SUCCESS;
    }
}
