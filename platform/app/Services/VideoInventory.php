<?php

namespace App\Services;

use App\Models\SourceRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/** Technical facts come from the first active linked original, never from a guessed file. */
class VideoInventory
{
    public function apply(Builder $query, array $filters): Builder
    {
        $asset = DB::table('media as video_asset')
            ->where('video_asset.kind', 'video')
            ->whereNull('video_asset.archived_at')
            ->where(function (QueryBuilder $links): void {
                $links->whereExists(function (QueryBuilder $usage): void {
                    $usage->selectRaw('1')
                        ->from('media_usages')
                        ->whereColumn('media_usages.media_id', 'video_asset.id')
                        ->where('media_usages.subject_type', SourceRecord::class)
                        ->whereColumn('media_usages.subject_id', 'source_records.id');
                });
                $this->orWhereJsonArrayContains($links, '$.media_ids');
                $this->orWhereJsonArrayContains($links, '$.media.video');
                $links->orWhere(function (QueryBuilder $youtube): void {
                    $youtube->where('source_records.source', 'youtube')
                        ->whereRaw($this->jsonValue('video_asset.metadata', '$.youtube_id').' = source_records.source_id');
                });
            })
            ->orderBy('video_asset.id')
            ->limit(1);

        $asset->where(function (QueryBuilder $links): void {
            $links->whereNull('source_records.metadata->excluded_media_ids')
                ->orWhereRaw('NOT ('.$this->jsonArrayContains('$.excluded_media_ids').')');
        });

        $grammar = DB::connection()->getQueryGrammar();
        $facts = array_map(
            fn (string $key): string => "NULLIF(".$grammar->wrap($key).", 'null')",
            ['video_asset.metadata->technical->duration', 'video_asset.metadata->duration', 'source_records.metadata->duration']
        );
        $duration = (clone $asset)->selectRaw('CAST(COALESCE('.implode(',', $facts).') AS DECIMAL(20,3))');
        $bytes = (clone $asset)->select('video_asset.bytes');
        $technical = $grammar->wrap('video_asset.metadata->technical_status');
        $state = (clone $asset)->selectRaw("CASE WHEN $technical IN ('queued','processing','failed') THEN $technical ELSE video_asset.status END");

        $videoId = (clone $asset)->select('video_asset.id');
        $query->addSelect(['video_id' => $videoId, 'video_duration' => $duration, 'video_bytes' => $bytes, 'video_processing' => $state]);
        foreach (['duration_min' => ['>=', $duration], 'duration_max' => ['<=', $duration], 'bytes_min' => ['>=', $bytes], 'bytes_max' => ['<=', $bytes]] as $key => [$operator, $subquery]) {
            if (isset($filters[$key])) {
                $query->whereRaw('('.$subquery->toSql().') '.$operator.' CAST(? AS DECIMAL(20,3))', [...$subquery->getBindings(), $filters[$key]]);
            }
        }
        if (! empty($filters['processing'])) {
            $query->whereRaw('('.$state->toSql().') = ?', [...$state->getBindings(), $filters['processing']]);
        }

        return $query;
    }

    private function orWhereJsonArrayContains(QueryBuilder $query, string $path): void
    {
        $query->orWhereRaw($this->jsonArrayContains($path));
    }

    private function jsonArrayContains(string $path): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return 'EXISTS (SELECT 1 FROM json_each(source_records.metadata, '.DB::connection()->getPdo()->quote($path).') WHERE json_each.value = CAST(video_asset.id AS TEXT))';
        }

        return "JSON_CONTAINS(JSON_EXTRACT(source_records.metadata, '$path'), JSON_QUOTE(CAST(video_asset.id AS CHAR)))";
    }

    private function jsonValue(string $column, string $path): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "json_extract($column, '$path')";
        }

        return "JSON_UNQUOTE(JSON_EXTRACT($column, '$path'))";
    }
}
