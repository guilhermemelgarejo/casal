<?php

namespace App\Support;

use Illuminate\Http\Request;

final class GoogleLogin
{
    public const FLOW_TTL = 600;

    public static function enabled(): bool
    {
        return (bool) config('services.google.enabled')
            && filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    public static function isRecent(?int $timestamp, int $ttl = self::FLOW_TTL): bool
    {
        return $timestamp !== null && $timestamp <= time() && $timestamp >= time() - $ttl;
    }

    public static function confirmed(Request $request, string $purpose): bool
    {
        $proof = $request->session()->get('google_confirmation', []);

        return self::enabled()
            && $request->user() !== null
            && ($proof['user_id'] ?? null) === $request->user()->id
            && ($proof['purpose'] ?? null) === $purpose
            && self::isRecent($proof['at'] ?? null, 300);
    }
}
