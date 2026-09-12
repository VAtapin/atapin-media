<?php
namespace App\Services\Importing;
use App\Models\ImportRun;

/** Soft time budget: finish an atomic file/row before yielding, never interrupt its write. */
class ImportWorkBudget
{
    private ?string $runId = null;
    private float $deadline = 0;
    private bool $unitStarted = false;
    public function __construct(private float $seconds = 30) {}
    public function begin(ImportRun $run): void
    {
        $this->runId = $run->source === 'youtube-takeout' ? $run->id : null;
        $this->deadline = microtime(true) + $this->seconds;
        $this->unitStarted = false;
    }
    public function boundary(ImportRun $run): void
    {
        if ($this->runId !== $run->id) return;
        if ($this->unitStarted && microtime(true) >= $this->deadline) {
            app(ImportProgress::class)->checkpoint($run, null, [], true);
            throw new ImportYielded;
        }
        $this->unitStarted = true;
    }
    public function end(): void { $this->runId = null; }
}
