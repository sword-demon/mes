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
        // `a|b` lets one shared lookup serve both the list and create permissions.
        $codes = array_filter(explode('|', $permission));
        $allowed = $user !== null
            && $codes !== []
            && collect($codes)->contains(fn (string $code): bool => $this->access->allows($user, $code));

        abort_unless($allowed, 403, 'Forbidden.');

        return $next($request);
    }
}
