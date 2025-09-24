<?php

namespace Miguilim\Helpers;

use Closure;
use Miguilim\Helpers\IpAddress;
use Illuminate\Http\Request;

class IpValidationOnAuthMiddleware
{
    use Concerns\HasRouteFunctions;

    public function handle(Request $request, Closure $next)
    {
        if (in_array($this->getCurrentRouteName($request), $this->routes)) {
            if ($request->method() === 'POST') {
                $isBlock = IpAddress::find($request->ip())->block;

                if ($isBlock) {
                    return back()->with('error', __('VPN, Tor or Proxy detected! Please disable any type of service that may mask your IP to proceed.'));
                }
            }
        }

        return $next($request);
    }
}
