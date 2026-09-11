<?php
namespace App\Services;
use App\Models\AuditEvent;
use Illuminate\Support\Facades\Auth;
class Audit
{
    public function record(string $action, ?string $subject = null, array $context = []): void
    {
        AuditEvent::create(['user_id' => Auth::id(), 'action' => $action, 'subject' => $subject,
            'context' => $context, 'created_at' => now()]);
    }
}
