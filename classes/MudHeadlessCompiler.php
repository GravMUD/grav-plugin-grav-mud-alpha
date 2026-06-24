<?php

declare(strict_types=1);

namespace Grav\Plugin\GravMudAlpha;

use Grav\Common\Grav;

/** Shared MudAlphaCompiler setup for headless API renders (no full page pipeline). */
final class MudHeadlessCompiler
{
    public static function compile(Grav $grav, string $raw): string
    {
        require_once __DIR__ . '/MudAlphaCompiler.php';

        $compiler = new MudAlphaCompiler();
        $compiler->setGrav($grav);

        $theme = (string) $grav['config']->get('system.pages.theme', 'grav-mud-getgrav');
        $compiler->setAssetBase($grav['base_url'] . '/user/themes/' . $theme . '/images');

        if ($grav['config']->get('plugins.grav-mud-commentz.enabled')) {
            $commentRoute = trim((string) $grav['config']->get('plugins.grav-mud-commentz.api_route', 'api/mud-commentz'), '/');
            $compiler->setCommentApiBase('/' . $commentRoute);
        }

        $msgCfg = (array) $grav['config']->get('plugins.messenger', []);
        if ($msgCfg === []) {
            $msgCfg = (array) $grav['config']->get('plugins.grav-mud-messenger', []);
        }
        if (!empty($msgCfg['enabled'])) {
            $messengerRoute = trim((string) ($msgCfg['api_route'] ?? 'api/mud-messenger'), '/');
            if (class_exists(\Grav\Plugin\Api\ApiRouteCollector::class)) {
                $messengerRoute = 'api/v1/mud-messenger';
            }
            $compiler->setMessengerApiBase('/' . $messengerRoute);
        }

        if ($grav['config']->get('plugins.grav-mud-marketplace.enabled')) {
            $shopRoute = trim((string) $grav['config']->get('plugins.grav-mud-marketplace.route_prefix', 'shop'), '/');
            $compiler->setMarketplaceRoute($shopRoute);
        }

        return $compiler->compile($raw);
    }
}
