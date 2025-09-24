<?php

namespace Miguilim\Helpers\Concerns;

use Illuminate\Http\Request;

trait HasRouteFunctions
{
    protected array $routes = [
        'login', 'register', 'password.email', 'verification.send', 'verification.notice'
    ];

    protected function getCurrentRouteName(Request $request): string
    {
        if ($request->segment(1) === 'login') {
            return 'login';
        }
        if ($request->segment(1) === 'register') {
            return 'register';
        }
        if ($request->segment(1) === 'forgot-password') {
            return 'password.email';
        }
        if ($request->segment(1) === 'email' && $request->segment(2) === 'verification-notification') {
            return 'verification.send';
        }
        if ($request->segment(1) === 'email' && $request->segment(2) === 'verify') {
            return 'verification.notice';
        }

        return $request->route()?->getName() ?? '';
    }
}
