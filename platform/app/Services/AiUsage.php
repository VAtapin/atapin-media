<?php

namespace App\Services;

use App\Models\DesktopAiRequest;

class AiUsage
{
    public function record(DesktopAiRequest $entry, array $usage, ?string $model = null): void
    {
        $input = max(0, (int) ($usage['input_tokens'] ?? 0));
        $output = max(0, (int) ($usage['output_tokens'] ?? 0));
        $total = max(0, (int) ($usage['total_tokens'] ?? ($input + $output)));
        // The provider response exposes usage, not a reliable tariff. Keep token facts
        // and leave cost empty instead of using manually entered or stale prices.
        $entry->update(['model'=>$model ?: app(Settings::class)->get('ai_model'),'input_tokens'=>$input,'output_tokens'=>$output,'total_tokens'=>$total,'estimated_cost_micros'=>null]);
    }
}
