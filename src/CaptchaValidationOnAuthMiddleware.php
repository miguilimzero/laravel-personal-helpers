<?php

namespace Miguilim\Helpers;

use Closure;
use Miguilim\Helpers\CaptchaValidator;
use Illuminate\Http\Request;

class CaptchaValidationOnAuthMiddleware
{
    use Concerns\HasRouteFunctions;

    public function handle(Request $request, Closure $next)
    {
        if (in_array($this->getCurrentRouteName($request), $this->routes)) {
            if ($request->method() === 'GET') {
                if (class_exists('Inertia\Inertia')) {
                    \Inertia\Inertia::share([
                        'captcha.driver' => config('captcha.driver'),
                        'captcha.siteKey' => config('captcha.site_key'),
                    ]);
                }
            }

            if ($request->method() === 'POST') {
                $response = CaptchaValidator::defaultDriver()->validate(
                    token: (string) $request->input('captcha_token'),
                    action: str_replace('.', '_', $this->getCurrentRouteName($request)),
                );

                if (! $response->success) {
                    return back()->with('error', __('Invalid captcha answer. Please complete the challenge correctly.'));
                }
            }
        }

        return $next($request);
    }
}
