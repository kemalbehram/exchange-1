<?php

namespace App\Http\Middleware;

use Closure;

class ValidChainPush
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $chain_push_ip_list = \App\Models\Setting::getValueByExplode('chain_push_ip_list');
        $ip = $request->ip();

        if (is_array($chain_push_ip_list) && !in_array($ip, $chain_push_ip_list)) {
            return response()->json([
                'type' => 'error',
                'message' => '您无权访问',
            ]);
        }
        return $next($request);
    }
}
