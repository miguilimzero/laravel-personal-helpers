<?php

namespace Miguilim\Helpers;

use Closure;
use Miguilim\Helpers\CaptchaValidator;
use Miguilim\Helpers\IpAddress;
use Illuminate\Http\Request;

class ExtraValidationOnAuthMiddleware
{
    protected array $routes = [
        'login', 'register', 'password.email', 'verification.send'
    ];

    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (in_array($this->getCurrentRouteName($request), $this->routes)) {
            if ($request->method() === 'GET') {
                if (class_exists('Inertia\Inertia')) {
                    \Inertia\Inertia::share([
                        'captcha.driver' => config('captcha.driver'),
                        'captcha.siteKey' => config('captcha.siteKey'),
                    ]);
                }
            }

            if ($request->method() === 'POST') {
                if ($captchaResponse = $this->validateCaptcha($request)) {
                    return $captchaResponse;
                }

                if ($ipResponse = $this->validateIpAddress($request)) {
                    return $ipResponse;
                }
            }
        }

        return $next($request);
    }

    protected function validateCaptcha(Request $request)
    {
        $response = CaptchaValidator::defaultDriver()->validate(
            token: (string) $request->input('captcha_token'),
            action: str_replace('.', '_', $this->getCurrentRouteName($request)),
        );

        if (! $response->success) {
            return back()->with('error', __('Invalid captcha answer. Please complete the challenge correctly.'));
        }
    }

    protected function validateIpAddress(Request $request)
    {
        $isBlock = IpAddress::find($request->ip())->block;

        if ($isBlock) {
            return back()->with('error', __('VPN, Tor or Proxy detected! Please disable any type of service that may mask your IP to proceed.'));
        }
    }

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

        return $request->route()?->getName() ?? '';
    }
}
