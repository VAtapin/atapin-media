<?php

namespace App\Http\Controllers;

use App\Models\{Media,SourceRecord,TaxonomyTerm,NewsletterSubscription};
use App\Services\StripePayments;
use Illuminate\Support\Facades\Gate;

class MigrationWorkspaceController extends Controller
{
    public function __invoke()
    {
        $steps=[];
        if(Gate::allows('settings.manage'))$steps[]=['id'=>'branding','app'=>'settings'];
        if(Gate::allows('media.view')){
            $steps[]=['id'=>'documents','app'=>'media','count'=>Media::whereNull('archived_at')->whereIn('kind',['document','pdf'])->count()];
            $steps[]=['id'=>'archive','app'=>'media','count'=>Media::whereNull('archived_at')->whereIn('kind',['video','audio','image'])->count()];
        }
        if(Gate::allows('integrations.manage'))$steps[]=['id'=>'platforms','app'=>'integrations'];
        if(Gate::allows('content.edit'))$steps[]=['id'=>'taxonomy','app'=>'topics','count'=>TaxonomyTerm::where('active',true)->count()];
        if(Gate::allows('subscribers.manage'))$steps[]=['id'=>'subscribers','app'=>'newsletter','count'=>NewsletterSubscription::where('status','active')->whereNotNull('consented_at')->whereNotNull('confirmed_at')->count()];
        if(Gate::allows('shop.manage'))$steps[]=['id'=>'payments','app'=>'shop','optional'=>true,'configured'=>app(StripePayments::class)->ready()];
        if(Gate::allows('content.edit'))$steps[]=['id'=>'review','app'=>'videos','count'=>SourceRecord::where('source','!=','catalog-reset')->whereIn('kind',['video','short','post'])->where(fn($q)=>$q->whereNull('metadata->archive_data')->orWhere('metadata->archive_data',false))->where(fn($q)=>$q->where('status','review')->orWhere('metadata->external_sync_pending_review',true))->count()];
        return response()->json(['steps'=>$steps])->header('Cache-Control','no-store');
    }
}
