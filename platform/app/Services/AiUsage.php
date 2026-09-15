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
        $inputRate = app(Settings::class)->get('ai_input_price_per_million_usd');
        $outputRate = app(Settings::class)->get('ai_output_price_per_million_usd');
        $cost = $total > 0 && is_numeric($inputRate) && is_numeric($outputRate)
            ? (int) round((($input / 1_000_000) * (float) $inputRate + ($output / 1_000_000) * (float) $outputRate) * 1_000_000)
            : null;
        $entry->update(['model'=>$model ?: app(Settings::class)->get('ai_model'),'input_tokens'=>$input,'output_tokens'=>$output,'total_tokens'=>$total,'estimated_cost_micros'=>$cost]);
    }
}
