<?php

namespace Miguilim\Helpers\Concerns;

trait ExtraAgentFeatures
{
    public const DEVICE_TYPE_DESKTOP = 1;
    public const DEVICE_TYPE_MOBILE = 2;
    public const DEVICE_TYPE_TABLET = 3;

    public static function currentRequest(): static
    {
        $instance = new static();
        $instance->setUserAgent((string) optional(request())->header('User-Agent'));

        return $instance;
    }

    public static function make(string $userAgent): static
    {
        $instance = new static();
        $instance->setUserAgent($userAgent);

        return $instance;
    }

    public function browserFull(): string
    {
        $browser = $this->browser();
        $version = $this->version($browser ?? '');

        return trim("{$browser} {$version}");
    }

    public function deviceType(): int
    {
        if ($this->isTablet()) { // Must verify tablet before mobile (Not 100% confirmed)
            return self::DEVICE_TYPE_TABLET;
        }

        if ($this->isMobile()) {
            return self::DEVICE_TYPE_MOBILE;
        }

        return self::DEVICE_TYPE_DESKTOP;
    }
}
