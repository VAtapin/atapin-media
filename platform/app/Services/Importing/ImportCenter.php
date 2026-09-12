<?php
namespace App\Services\Importing;

use App\Models\ImportRun;
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
        ImportArchive::dispatch($run->id);
        return $run;
    }

    public function run(ImportRun $run): void
    {
        $run->update(['status' => 'running', 'started_at' => now()]);
        try {
            $this->adapter($run->source)->import($run);
            $run->update([
                'status' => $run->notes ? 'partial' : 'complete',
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            $run->increment('skipped');
            $run->update([
                'status' => 'failed',
                'source_kind' => $run->source,
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);
            throw $e;
        }
    }
}
