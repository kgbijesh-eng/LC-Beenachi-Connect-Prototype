<?php

namespace App\Http\Middleware;

use App\Models\Club;
use App\Services\Access\InstanceOrganisation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureInstanceOrganisation
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $club = $route?->parameter('club');
        if (! $route?->hasParameter('club') && ! $request->is('api/v1/auth/login', 'api/v1/auth/me', 'api/v1/auth/activate')) {
            return $next($request);
        }

        $organisation = app(InstanceOrganisation::class)->resolve();
        $request->attributes->set('instance_organisation', $organisation);
        if ($route->hasParameter('club')) {
            $clubId = $club instanceof Club ? (string) $club->getKey() : (string) $club;
            abort_unless($clubId === (string) $organisation->getKey(), 404, 'Not found.');
        }

        return $next($request);
    }
}
