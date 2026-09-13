<?php

namespace App\Services\Publishing;

use App\Contracts\PublishingConnector;
use App\Services\Publishing\Connectors\MetaConnector;
use App\Services\Publishing\Connectors\TelegramConnector;
use App\Services\Publishing\Connectors\YouTubeConnector;

class ConnectorRegistry
{
    /** @var array<string, PublishingConnector> */
    private array $connectors;

    public function __construct(ConnectionStore $connections, YouTubeClient $youtube)
    {
        $this->connectors = [
            'youtube' => new YouTubeConnector($youtube, $connections),
            'facebook' => new MetaConnector('facebook', $connections),
            'instagram' => new MetaConnector('instagram', $connections),
            'telegram' => new TelegramConnector($connections),
        ];
    }

    public function get(string $provider): PublishingConnector
    {
        if (! isset($this->connectors[$provider])) throw new UnsupportedCapability('No connector is installed for '.$provider.'.');
        return $this->connectors[$provider];
    }

    /** @return array<string, PublishingConnector> */
    public function all(): array
    {
        return $this->connectors;
    }
}
