<?php

namespace App\Services\Publishing\Connectors;

use App\Contracts\PublishingConnector;
use App\Models\Publication;
use App\Services\Publishing\{ConnectionStore, UnsupportedCapability};

class RtmpConnector implements PublishingConnector
{
    public function __construct(private readonly string $provider, private readonly ConnectionStore $connections) {}
    public function provider(): string { return $this->provider; }
    public function capabilities(): array { return ['video' => false, 'short' => false, 'post' => false, 'live' => true]; }
    public function publish(Publication $publication): array
    {
        if ($publication->record?->publishingKind() !== 'live') throw new UnsupportedCapability('RTMP destinations accept only Live streams.');
        if (! $this->connections->connected($this->provider)) throw new UnsupportedCapability('The RTMP destination was removed.');
        return ['external_id' => $this->provider, 'remote_status' => ($publication->record->metadata['live_status'] ?? null) === 'ended' ? 'ended' : 'scheduled'];
    }
}
