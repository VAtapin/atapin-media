<?php

namespace App\Services\Publishing;

use App\Contracts\PublishingConnector;
use App\Services\Publishing\Connectors\MetaConnector;
use App\Services\Publishing\Connectors\TelegramConnector;
use App\Services\Publishing\Connectors\YouTubeConnector;
use App\Services\Publishing\Connectors\RtmpConnector;
use App\Services\Publishing\Connectors\XConnector;

class ConnectorRegistry
{
    /** @var array<string, PublishingConnector> */
    private array $connectors;

    public function __construct(private readonly ConnectionStore $connections, YouTubeClient $youtube)
    {
        $this->connectors = [
            'youtube' => new YouTubeConnector($youtube, $connections),
            'facebook' => new MetaConnector('facebook', $connections),
            'instagram' => new MetaConnector('instagram', $connections),
            'telegram' => new TelegramConnector($connections),
            'x' => new XConnector(new XClient($connections)),
        ];
    }

    public function get(string $provider): PublishingConnector
    {
        if (str_starts_with($provider, 'rtmp_') && $this->connections->connected($provider)) return new RtmpConnector($provider, $this->connections);
        if (! isset($this->connectors[$provider])) throw new UnsupportedCapability('No connector is installed for '.$provider.'.');
        return $this->connectors[$provider];
    }

    /** @return array<string, PublishingConnector> */
    public function all(): array
    {
        return $this->connectors;
    }
}
