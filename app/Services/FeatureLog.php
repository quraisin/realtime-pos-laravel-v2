<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class FeatureLog
{
    public const CHANNEL_AUTH = 'auth';
    public const CHANNEL_POS = 'pos';
    public const CHANNEL_TRANSACTION = 'transaction';
    public const CHANNEL_KDS = 'kds';
    public const CHANNEL_CMS = 'cms';
    public const CHANNEL_PRINTER = 'printer';
    public const CHANNEL_ANALYTICS = 'analytics';

    /**
     * Log a feature-specific event to both its dedicated channel log file and the main application log.
     *
     * @param string $feature Module identifier (auth, pos, transaction, kds, cms, printer, analytics)
     * @param string $action Specific action/event name (e.g. ORDER_CREATED, LOGIN_SUCCESS, VOID_ORDER)
     * @param string $message Human-readable descriptive log message
     * @param array $context Additional structured payload metadata
     * @param string $level PSR-3 log level (info, warning, error, debug, notice)
     */
    public static function log(
        string $feature,
        string $action,
        string $message,
        array $context = [],
        string $level = 'info'
    ): void {
        $feature = strtolower($feature);
        $tagFeature = strtoupper($feature);
        $tagAction = strtoupper($action);

        $formattedMessage = sprintf("[%s] [%s] %s", $tagFeature, $tagAction, $message);

        // Enrich context with operational request details
        try {
            if (!isset($context['ip']) && Request::hasSession()) {
                $context['ip'] = Request::ip();
            }
            if (!isset($context['user_id']) && Auth::check()) {
                $user = Auth::user();
                $context['user_id'] = $user->id;
                $context['user_name'] = $user->name ?? $user->username;
            }
        } catch (\Throwable $e) {
            // Ignore context enrichment errors in CLI or test mocks
        }

        // 1. Write to module-specific channel (e.g., storage/logs/{feature}.log)
        try {
            Log::channel($feature)->{$level}($formattedMessage, $context);
        } catch (\Throwable $e) {
            // Channel may not exist in non-standard test environments
        }

        // 2. Write to main application log (storage/logs/laravel.log)
        try {
            Log::channel('single')->{$level}($formattedMessage, $context);
        } catch (\Throwable $e) {
            try {
                Log::{$level}($formattedMessage, $context);
            } catch (\Throwable $e2) {
                // Ignore fallback error
            }
        }
    }

    /**
     * Log Authentication & Access event
     */
    public static function auth(string $action, string $message, array $context = [], string $level = 'info'): void
    {
        self::log(self::CHANNEL_AUTH, $action, $message, $context, $level);
    }

    /**
     * Log Cashier POS event
     */
    public static function pos(string $action, string $message, array $context = [], string $level = 'info'): void
    {
        self::log(self::CHANNEL_POS, $action, $message, $context, $level);
    }

    /**
     * Log Transaction History & Void event
     */
    public static function transaction(string $action, string $message, array $context = [], string $level = 'info'): void
    {
        self::log(self::CHANNEL_TRANSACTION, $action, $message, $context, $level);
    }

    /**
     * Log Kitchen Display System (KDS) event
     */
    public static function kds(string $action, string $message, array $context = [], string $level = 'info'): void
    {
        self::log(self::CHANNEL_KDS, $action, $message, $context, $level);
    }

    /**
     * Log Superadmin CMS (Products, Categories, Stock) event
     */
    public static function cms(string $action, string $message, array $context = [], string $level = 'info'): void
    {
        self::log(self::CHANNEL_CMS, $action, $message, $context, $level);
    }

    /**
     * Log Thermal Printer & Hardware Spooler event
     */
    public static function printer(string $action, string $message, array $context = [], string $level = 'info'): void
    {
        self::log(self::CHANNEL_PRINTER, $action, $message, $context, $level);
    }

    /**
     * Log Analytics & Reporting event
     */
    public static function analytics(string $action, string $message, array $context = [], string $level = 'info'): void
    {
        self::log(self::CHANNEL_ANALYTICS, $action, $message, $context, $level);
    }
}
