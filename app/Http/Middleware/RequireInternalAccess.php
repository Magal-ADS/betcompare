<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireInternalAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('oddradar.access.enabled')) {
            return $next($request);
        }

        $username = config('oddradar.access.username');
        $password = config('oddradar.access.password');

        if (! is_string($username) || ! is_string($password) || $username === '' || $password === '') {
            abort(503, 'Configure as credenciais de acesso interno do OddRadar.');
        }

        if (! hash_equals($username, (string) $request->getUser()) || ! hash_equals($password, (string) $request->getPassword())) {
            return response('Credenciais necessárias.', 401, ['WWW-Authenticate' => 'Basic realm="OddRadar"']);
        }

        return $next($request);
    }
}
