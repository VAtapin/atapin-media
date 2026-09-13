<?php

namespace App\Services;

use App\Models\{PublicContentView, SourceRecord};
use Illuminate\Http\Request;

class PublicViewCounter
{
    public function record(SourceRecord $record, Request $request): int
    {
        $identity = $request->user()
            ? 'user:'.$request->user()->getAuthIdentifier()
            : 'session:'.$request->session()->getId();
        $visitorHash = hash_hmac('sha256', $identity, (string) config('app.key'));
        $date = now(config('app.timezone'))->toDateString();

        PublicContentView::query()->insertOrIgnore([
            'record_id' => $record->id,
            'visitor_hash' => $visitorHash,
            'viewed_on' => $date,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) PublicContentView::query()->where('record_id', $record->id)->count();
    }
}
