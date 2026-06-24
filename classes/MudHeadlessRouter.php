<?php

declare(strict_types=1);

namespace Grav\Plugin\GravMudAlpha;

use Grav\Common\Grav;

class MudHeadlessRouter
{
    private Grav $grav;
    /** @var array<string, mixed> */
    private array $config;

    /** @param array<string, mixed> $config */
    public function __construct(Grav $grav, array $config)
    {
        $this->grav = $grav;
        $this->config = $config;
    }

    public function handle(): void
    {
        $path = trim((string) $this->grav['uri']->path(), '/');
        $apiPrefix = trim((string) ($this->config['headless_api_route'] ?? 'api/mud'), '/');

        if ($path === $apiPrefix || str_starts_with($path, $apiPrefix . '/')) {
            require_once __DIR__ . '/MudHeadlessApi.php';
            (new MudHeadlessApi($this->grav, $this->config))->handle($path, $apiPrefix);
            exit;
        }
    }
}
