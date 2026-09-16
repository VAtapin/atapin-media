<?php

namespace Tests\Feature;

use App\Models\KaiKnowledgeEntry;
use App\Services\AdminKnowledgeBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KaiKnowledgeIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_and_current_public_menu_are_synchronized_without_losing_planned_status(): void
    {
        $knowledge=app(AdminKnowledgeBase::class);
        $count=$knowledge->sync();
        $this->assertGreaterThan(40,$count);
        $this->assertSame($count,KaiKnowledgeEntry::where('active',true)->count());
        $this->assertSame('planned',KaiKnowledgeEntry::findOrFail('tiktok-linkedin-planned')->status);
        $this->assertSame('unverified',KaiKnowledgeEntry::findOrFail('browser-live-production-unverified')->status);
        $this->assertStringContainsString('/buecher',KaiKnowledgeEntry::findOrFail('public-nav-buecher')->content);
        $this->assertSame($count,$knowledge->sync());
        KaiKnowledgeEntry::create(['key'=>'stale-controlled','title'=>'Old','status'=>'implemented','keywords'=>['old'],
            'content'=>'Old instruction','sources'=>'test','source_hash'=>hash('sha256','old'),'active'=>true]);
        $knowledge->sync();
        $this->assertFalse(KaiKnowledgeEntry::findOrFail('stale-controlled')->active);
        $this->assertContains('planned',array_column($knowledge->context('TikTok'),'status'));
    }
}
