<?php

declare(strict_types=1);

namespace Grav\Plugin\GravMudAlpha;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Framework\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class MudHeadlessApiBridgeController
{
    public function __construct(
        protected readonly Grav $grav,
        protected readonly Config $config,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() === 'OPTIONS') {
            return new Response(204, ['Access-Control-Allow-Origin' => '*']);
        }

        $params = $request->getAttribute('route_params', []);
        $sub = isset($params['subpath']) ? trim((string) $params['subpath'], '/') : '';
        $apiPrefix = trim((string) $this->config->get('plugins.grav-mud-alpha.headless_api_route', 'api/mud'), '/');
        $path = $apiPrefix . ($sub !== '' ? '/' . $sub : '');

        require_once __DIR__ . '/MudHeadlessApi.php';
        $cfg = (array) $this->config->get('plugins.grav-mud-alpha', []);
        $api = new MudHeadlessApi($this->grav, $cfg);
        $api->setBridgeMode(true);

        $level = ob_get_level();
        ob_start();
        try {
            $api->handle($path, $apiPrefix);
        } finally {
            $output = (string) ob_get_clean();
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }

        $code = 200;
        $decoded = json_decode($output, true);
        if (is_array($decoded) && ($decoded['ok'] ?? null) === false) {
            $code = 404;
        }

        return new Response($code, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Access-Control-Allow-Origin' => '*',
        ], $output);
    }
}
