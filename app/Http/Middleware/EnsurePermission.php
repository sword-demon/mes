<?php

namespace App\Http\Middleware;

use App\Modules\System\Service\AccessControlService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function __construct(private readonly AccessControlService $access) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        abort_unless($user !== null && $this->access->allows($user, $permission), 403, 'Forbidden.');

        return $next($request);
    }
}
