<?php

namespace Miguilim\Helpers;

use InvalidArgumentException;
use Illuminate\Support\Facades\Http;

class CaptchaValidator
{
    /**
     * Construct method.
     */
    public function __construct(protected string $driver, protected string $secretKey, protected string $siteKey)
    {
    }

    /**
     * Instantiate class with default driver and configs.
     */
    public static function defaultDriver(): static
    {
        return new static(config('captcha.driver'), config('captcha.secret_key'), config('captcha.site_key'));
    }

    /**
     * Validate captcha token response.
     */
    public function validate(string $token, ?string $action = null, array $extraParams = []): object
    {
        return (object) match ($this->driver) {
            'turnstile' => $this->validateTurnstile($token, $action, $extraParams),
            'hcaptcha'  => $this->validateHCaptcha($token, $extraParams),
            'recaptcha' => $this->validateReCaptcha($token, $extraParams),
            'geetest'   => $this->validateGeeTest($token),
            default     => ['success' => false, 'extra' => []],
        };
    }

    /**
     * Validate using Turnstile driver.
     */
    protected function validateTurnstile(string $token, ?string $action, array $extraParams): array
    {
        $captcha = (object) Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', array_merge($extraParams, [
            'secret'   => $this->secretKey,
            'response' => $token,
        ]))->throw()->json();

        if ($captcha?->success !== true) {
            return [
                'success' => false,
                'extra'   => []
            ];
        }

        if ($action !== null && isset($captcha->action) && $captcha->action !== $action) {
            return [
                'success' => false,
                'extra'   => [
                    'response_action' => $captcha->action,
                ]
            ];
        }

        return [
            'success' => true,
            'extra'   => [
                'cdata' => $captcha?->cdata
            ]
        ];
    }

    /**
     * Validate using hCaptcha driver.
     */
    protected function validateHCaptcha(string $token, array $extraParams): array
    {
        $captcha = (object) Http::asForm()->post('https://hcaptcha.com/siteverify', array_merge($extraParams, [
            'secret'   => $this->secretKey,
            'response' => $token,
        ]))->throw()->json();

        return [
            'success' => $captcha?->success === true,
            'extra'   => []
        ];
    }

    /**
     * Validate using reCaptcha driver.
     */
    protected function validateReCaptcha(string $token, array $extraParams): array
    {
        $captcha = (object) Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', array_merge($extraParams, [
            'secret'   => $this->secretKey,
            'response' => $token,
        ]))->throw()->json();

        return [
            'success' => $captcha?->success === true,
            'extra'   => []
        ];
    }

    /**
     * Validate using GeeTest driver.
     */
    protected function validateGeeTest(string $token): array
    {
        $token = explode('.', $token);

        if (count($token) !== 4) {
            throw new InvalidArgumentException('Invalid token format');
        }

        $signToken = hash_hmac('sha256', $token[0], $this->secretKey);

        $captcha = (object) Http::asForm()->post('http://gcaptcha4.geetest.com/validate', [
            'lot_number'   => $token[0],
            'captcha_output' => $token[1],
            'pass_token' => $token[2],
            'gen_time'  => $token[3],
            'sign_token' => $signToken,
            'captcha_id' => $this->siteKey,
        ])->throw()->json();

        return [
            'success' => $captcha?->result === 'success',
            'extra'   => []
        ];
    }
}
