<?php

declare(strict_types=1);

namespace Grav\Plugin\GravMudAlpha;

use Grav\Common\Grav;

class MudHeadlessApi
{
    private Grav $grav;
    /** @var array<string, mixed> */
    private array $config;
    private bool $bridgeMode = false;

    /** @param array<string, mixed> $config */
    public function __construct(Grav $grav, array $config)
    {
        $this->grav = $grav;
        $this->config = $config;
    }

    public function setBridgeMode(bool $enabled): void
    {
        $this->bridgeMode = $enabled;
    }

    public function handle(string $path, string $apiPrefix): void
    {
        if (!$this->bridgeMode) {
            header('Content-Type: application/json; charset=UTF-8');
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Accept');
            header('X-Content-Type-Options: nosniff');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            if (!$this->bridgeMode) {
                http_response_code(204);
            }
            return;
        }

        $sub = trim(substr($path, strlen($apiPrefix)), '/');
        try {
            if ($sub === '' || $sub === 'status') {
                $this->respond($this->status());
                return;
            }
            if ($sub === 'pages') {
                $this->respond($this->pagesList());
                return;
            }
            if (preg_match('#^page/(.+)$#', $sub, $m)) {
                $payload = $this->pageDetail($m[1]);
                if ($payload !== null) {
                    $this->respond($payload);
                }
                return;
            }
            $this->fail('not_found', 404);
        } catch (\Throwable $e) {
            $this->fail($e->getMessage(), 500);
        }
    }

    /** @return array<string, mixed> */
    private function status(): array
    {
        require_once __DIR__ . '/MudHeadlessConfig.php';

        $payload = [
            'ok' => true,
            'plugin' => 'grav-mud-alpha',
            'api' => 'headless',
            'version' => '0.7.3',
            'headless_mode' => strtolower((string) (MudHeadlessConfig::resolve($this->grav, $this->config)['headless_mode'] ?? 'all')),
        ];

        if (isset($_GET['diag']) && (string) $_GET['diag'] === '1') {
            require_once __DIR__ . '/MudHeadlessConfig.php';
            require_once __DIR__ . '/MudPublicPages.php';
            $children = 0;
            try {
                foreach ($this->grav['pages']->root()->children() as $_) {
                    $children++;
                }
            } catch (\Throwable) {
            }
            $payload['diag'] = [
                'children' => $children,
                'mud_pages' => count(MudPublicPages::listing($this->grav)),
                'filter' => MudPublicPages::headlessFilterConfig($this->grav),
                'config_file' => MudHeadlessConfig::fileMeta($this->grav),
                'config_runtime_mode' => strtolower((string) ($this->config['headless_mode'] ?? 'all')),
            ];
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function pagesList(): array
    {
        require_once __DIR__ . '/MudHeadlessConfig.php';
        require_once __DIR__ . '/MudPublicPages.php';

        return [
            'ok' => true,
            'items' => MudPublicPages::listing($this->grav),
        ];
    }

    /** @return array<string, mixed>|null */
    private function pageDetail(string $slug): ?array
    {
        require_once __DIR__ . '/MudHeadlessConfig.php';
        require_once __DIR__ . '/MudPublicPages.php';
        $page = MudPublicPages::get($this->grav, $slug);
        if ($page === null) {
            $this->fail('not_found', 404);

            return null;
        }

        return [
            'ok' => true,
            'page' => $page,
        ];
    }

    /** @param array<string, mixed> $payload */
    private function respond(array $payload): void
    {
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function fail(string $message, int $code): void
    {
        if (!$this->bridgeMode) {
            http_response_code($code);
        }
        echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_SLASHES);
    }
}
