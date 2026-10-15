<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Las cuentas demo son descartables: no pueden usarse desde el MCP. */
class RejectDemoUsers
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->user()?->is_demo, 403, 'Las cuentas demo no pueden usar el MCP.');

        return $next($request);
    }
}
