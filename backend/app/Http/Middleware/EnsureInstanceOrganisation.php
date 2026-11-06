<?php

namespace App\Http\Middleware;

use App\Models\Club;
use App\Services\Access\OrganisationAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureInstanceOrganisation
{
    public function __construct(private readonly OrganisationAccess $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        // The API group is protected by default, including future routes without
        // a club parameter. Exempt only these exact route/method combinations.
        if (($route?->uri() === 'api/v1/health' && in_array($request->method(), ['GET', 'HEAD'], true))
            || ($route?->uri() === 'api/v1/auth/logout' && $request->isMethod('POST'))) {
            return $next($request);
        }

        $club = $route?->parameter('club');
        $clubId = $route?->hasParameter('club')
            ? (string) ($club instanceof Club ? $club->getKey() : $club)
            : null;
        $organisation = $this->access->resolve($clubId);
        $request->attributes->set('instance_organisation', $organisation);

        return $next($request);
    }
}
