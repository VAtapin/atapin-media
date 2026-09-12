<?php

namespace App\Http\Controllers;

use App\Models\ImportRun;
use App\Services\Importing\ImportCenter;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ImportController extends Controller
{
    private const SOURCES = [
        ['id' => 'intake', 'label' => 'Intake'],
        ['id' => 'youtube', 'label' => 'YouTube-Archiv'],
        ['id' => 'youtube-takeout', 'label' => 'Google Takeout'],
        ['id' => 'local-folder', 'label' => 'Lokaler Ordner'],
        ['id' => 'local-archive', 'label' => 'Lokales Archiv (zip/tar/gz/tgz)'],
        ['id' => 'youtube-service', 'label' => 'YouTube (Link/Channel/Playlist)'],
        ['id' => 'tiktok', 'label' => 'TikTok'],
        ['id' => 'instagram', 'label' => 'Instagram'],
        ['id' => 'facebook-video', 'label' => 'Facebook Video'],
    ];

    private const TARGETS = [
        ['id' => 'media_library', 'label' => 'Media Library'],
        ['id' => 'videos', 'label' => 'Videos'],
        ['id' => 'posts', 'label' => 'Beiträge'],
        ['id' => 'shorts', 'label' => 'Shorts'],
        ['id' => 'comments', 'label' => 'Kommentare'],
        ['id' => 'polls', 'label' => 'Polls'],
    ];

    private const TARGET_IDS = ['media_library', 'videos', 'posts', 'shorts', 'comments', 'polls', 'mixed'];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'source' => 'nullable|string|max:64',
            'source_kind' => 'nullable|string|max:64',
            'status' => 'nullable|in:queued,running,stop_requested,cancelled,complete,partial,failed',
            'target_profile' => 'nullable|in:media_library,videos,posts,shorts,comments,polls,mixed',
            'page' => 'nullable|integer|min:1',
        ]);

        $query = ImportRun::orderByDesc('updated_at');
        if (($filters['source'] ?? null)) {
            $query->where('source', $filters['source']);
        }
        if (($filters['source_kind'] ?? null)) {
            $query->where('source_kind', $filters['source_kind']);
        }
        if (($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }
        if (($filters['target_profile'] ?? null)) {
            $query->where('target_profile', $filters['target_profile']);
        }

        $runs = $query->paginate(20)->withQueryString();
        $items = $runs->getCollection()->map(function (ImportRun $run) {
            $summary=app(\App\Services\Importing\ImportJournal::class)->summary($run);
            return [
                'id' => $run->id,
                'source' => $run->source,
                'source_kind' => $run->source_kind,
                'source_ref' => $run->source_ref,
                'status' => $run->status,
                'progress' => $run->progress,
                'summary'=>$summary,
                'report_url'=>route('imports.report',$run),
                'target_profile' => $run->target_profile,
                'discovered' => $summary ? array_sum($summary) : $run->discovered,
                'imported' => $summary ? ($summary['added']??0) : $run->imported,
                'skipped' => $summary ? ($summary['duplicate']??0) : $run->skipped,
                'notes' => $run->notes,
                'error' => $run->error,
                'created_at' => $run->created_at?->toIso8601String(),
                'updated_at' => $run->updated_at?->toIso8601String(),
                'started_at' => $run->started_at?->toIso8601String(),
                'finished_at' => $run->finished_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $runs->currentPage(),
                'last_page' => $runs->lastPage(),
                'total' => $runs->total(),
            ],
        ]);
    }

    public function options()
    {
        return response()->json([
            'sources' => self::SOURCES,
            'targets' => self::TARGETS,
            'accepted_fields' => [
                'path' => 'Path innerhalb private/import-inbox',
                'source_ref' => 'URL oder externe ID',
                'channel_id' => 'YouTube Channel-ID',
                'playlist_id' => 'YouTube Playlist-ID',
                'target_profile' => 'media_library | videos | posts | shorts | comments | polls',
            ],
        ]);
    }

    public function files(Request $request, \App\Services\Importing\ServerImportBrowser $browser)
    {
        $data = $request->validate(['path' => 'nullable|string|max:255']);
        return response()->json($browser->list($data['path'] ?? ''));
    }

    public function store(Request $request, Audit $audit, ImportCenter $center)
    {
        $data = $request->validate([
            'source' => 'required|string',
            'target_profile' => 'nullable|string|max:64',
            'path' => 'nullable|string|max:255',
            'media_id' => 'nullable|uuid',
            'source_ref' => 'nullable|string|max:255',
            'channel_id' => 'nullable|string|max:255',
            'playlist_id' => 'nullable|string|max:255',
            'only_unsorted' => 'nullable|boolean',
            'notes' => 'nullable|string|max:2000',
            'batch'=>'nullable|string|max:100','expected_parts'=>'nullable|integer|min:1|max:100',
        ]);

        $source = trim((string) $data['source']);
        if (! $this->supportsSource($source)) {
            return response()->json(['message' => 'Unsupported import source.'], 422);
        }

        if (($data['target_profile'] ?? null) !== null && ! in_array($data['target_profile'], self::TARGET_IDS, true)) {
            return response()->json(['message' => 'Invalid target profile.'], 422);
        }

        $run = $center->queueRun((string) $request->user()->id, $source, Arr::only($data, [
            'source_ref',
            'media_id',
            'path',
            'channel_id',
            'playlist_id',
            'only_unsorted',
            'notes',
            'target_profile',
            'batch','expected_parts',
        ]));

        $audit->record('import.queued', $run->id, [
            'source' => $run->source,
            'source_ref' => $run->source_ref,
            'target_profile' => $run->target_profile,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'queued',
                'import_id' => $run->id,
            ]);
        }

        return back()->with('status', __('ui.import_queued'));
    }

    public function retry(ImportRun $run, Audit $audit)
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($run) {
            $current = ImportRun::lockForUpdate()->findOrFail($run->id);
            abort_unless(in_array($current->status, ['failed', 'partial', 'cancelled'], true), 409);
            $current->update(['status' => 'queued', 'error' => null, 'notes' => [], 'started_at' => null,
                'finished_at' => null, 'progress' => $current->progress??[]]);
            dispatch((new \App\Jobs\ImportArchive($current->id))->afterCommit());
        });
        $audit->record('import.retried', (string) $run->id);
        return response()->json(['status' => 'queued', 'import_id' => $run->id]);
    }

    public function stop(ImportRun $run, Audit $audit)
    {
        $status = \Illuminate\Support\Facades\DB::transaction(function () use ($run) {
            $current = ImportRun::lockForUpdate()->findOrFail($run->id);
            abort_unless(in_array($current->status, ['queued', 'running', 'stop_requested', 'cancelled'], true), 409);
            if ($current->status === 'queued') $current->update(['status' => 'cancelled', 'finished_at' => now()]);
            elseif ($current->status === 'running') $current->update(['status' => 'stop_requested']);
            return $current->status;
        });
        $audit->record('import.stop_requested', (string) $run->id);
        return response()->json(['status' => $status, 'import_id' => $run->id]);
    }

    private function supportsSource(string $source): bool
    {
        $supported = array_column(self::SOURCES, 'id');
        return in_array($source, $supported, true);
    }
    public function report(Request $request,ImportRun $run)
    {
        $data=$request->validate(['page'=>'nullable|integer|min:1','outcome'=>'nullable|string|max:32','type'=>'nullable|string|max:32']);
        $query=\App\Models\ImportItem::where('import_run_id',$run->id)->whereNotIn('type',['archive-entry','checkpoint'])->orderBy('id');
        foreach(['outcome','type'] as $key)if($data[$key]??'')$query->where($key,$data[$key]);
        $page=$query->paginate(30);
        $items=$page->getCollection()->map(function($item)use($run) {
            $data=$item->toArray();
            $data['browser_url']=$item->type==='video-check'?url('/desktop/imports/'.$run->id.'/items/'.$item->id.'/browser'):null;
            if($item->subject_id && in_array($item->type,['file','connection','video-check'],true))$data['open_url']=route('media.details',$item->subject_id);
            elseif($item->subject_id && in_array($item->type,['video','short','post','poll','comment','content-check'],true))$data['open_url']=route('content.show',$item->subject_id);
            elseif($item->subject_id && $item->type==='playlist')$data['open_url']=route('content.playlist',$item->subject_id);
            return $data;
        });
        return response()->json(['data'=>$items,'summary'=>app(\App\Services\Importing\ImportJournal::class)->summary($run),
            'status'=>$run->status,'meta'=>['current_page'=>$page->currentPage(),'last_page'=>$page->lastPage(),'total'=>$page->total()]]);
    }
    public function takeout(\App\Services\Importing\TakeoutArchiveAdapter $adapter)
    {
        return response()->json($adapter->inventory());
    }
    public function retryItem(ImportRun $run,\App\Models\ImportItem $item,Audit $audit)
    {
        abort_unless($item->import_run_id===$run->id && $item->outcome==='failed',404);
        return $this->retry($run,$audit);
    }
}
