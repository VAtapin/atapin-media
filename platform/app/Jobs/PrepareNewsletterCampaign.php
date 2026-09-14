<?php
namespace App\Jobs;
use App\Models\NewsletterCampaign;
use App\Services\NewsletterCampaigns;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
class PrepareNewsletterCampaign implements ShouldQueue
{
    use Queueable;
    public int $tries=3;
    public int $timeout=180;
    public function backoff(): array{return [30,120];}
    public function __construct(public int $id){}
    public function handle(NewsletterCampaigns $service): void{if($campaign=NewsletterCampaign::find($this->id))$service->prepare($campaign);}
    public function failed(?\Throwable $error): void{NewsletterCampaign::whereKey($this->id)->where('status','preparing')->update(['status'=>'failed']);}
}
