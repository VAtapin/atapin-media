<?php
namespace App\Services\Importing;

use App\Models\ImportRun;
use App\Jobs\ImportArchive;
use RuntimeException;
use Throwable;

class ImportCenter
{
    /** @var array<string, ImportAdapter> */
    private array $adapters;

    public function __construct(iterable $adapters)
    {
        $this->adapters = [];
        foreach ($adapters as $adapter) {
            if (! $adapter instanceof ImportAdapter) {
                continue;
            }
            $this->adapters[$adapter->source()] = $adapter;
        }
    }

    public function adapter(string $source): ImportAdapter
    {
        if (! isset($this->adapters[$source])) {
            throw new RuntimeException('Unsupported import source.');
        }
        return $this->adapters[$source];
    }

    public function prepareInput(string $source, array $input): array
    {
        $adapter = $this->adapter($source);
        return $adapter->normalize($adapter->validate($input));
    }

    public function queueRun(string $userId, string $source, array $options): ImportRun
    {
        $adapter = $this->adapter($source);
        $normalized = $adapter->normalize($adapter->validate($options));
        $payload = [
            'source' => $normalized['source'],
            'source_kind' => $normalized['source_kind'],
            'source_ref' => $normalized['source_ref'],
            'source_options' => $normalized['source_options'] ?? [],
            'target_profile' => $normalized['target_profile'] ?? 'mixed',
            'user_id' => $userId,
            'discovered' => 0,
            'status' => 'queued',
        ];

        $run = ImportRun::create($payload);
        dispatch(new ImportArchive($run->id));
        return $run;
    }

    public function run(ImportRun $run): void
    {
        $claimed = \Illuminate\Support\Facades\DB::transaction(function () use ($run) {
            $current = ImportRun::lockForUpdate()->findOrFail($run->id);
            if ($current->status !== 'queued') return false;
            $current->update(['status' => 'running', 'started_at' => $current->started_at ?? now(), 'progress' => $current->progress ?: ['stage' => 'prepare']]);
            return true;
        });
        if (! $claimed) return;
        $run->refresh();
        app(ImportWorkBudget::class)->begin($run);
        try {
            app(ImportProgress::class)->checkpoint($run);
            $this->adapter($run->source)->import($run);
            $summary=app(ImportJournal::class)->summary($run);
            $warnings=array_sum(array_intersect_key($summary,array_flip(['failed','unsupported','ambiguous','unmatched','missing'])));
            $this->finish($run, ($run->fresh()->notes || $warnings) ? 'partial' : 'complete');
        } catch (ImportYielded) {
            \Illuminate\Support\Facades\DB::transaction(function () use ($run) {
                $current = ImportRun::lockForUpdate()->findOrFail($run->id);
                if ($current->status === 'stop_requested') {
                    $current->update(['status' => 'cancelled', 'finished_at' => now()]);
                    return;
                }
                $current->update(['status' => 'queued', 'progress' => $run->progress, 'error' => null]);
                dispatch(new ImportArchive($run->id))->afterCommit();
            });
        } catch (ImportStopped) {
            $this->finish($run, 'cancelled');
        } catch (Throwable $e) {
            if ($run->fresh()->status === 'stop_requested') { $this->finish($run, 'cancelled'); return; }
            $run->increment('skipped');
            $this->finish($run, 'failed', $e->getMessage());
            throw $e;
        } finally { app(ImportWorkBudget::class)->end(); }
    }

    private function finish(ImportRun $run, string $status, ?string $error = null): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($run, $status, $error) {
            $current = ImportRun::lockForUpdate()->findOrFail($run->id);
            if ($current->status === 'stop_requested') { $status = 'cancelled'; $error = null; }
            $current->update(['status' => $status, 'error' => $error, 'finished_at' => now()]);
        });
    }
}
