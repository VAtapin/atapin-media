<?php
namespace App\Http\Controllers;
use App\Services\{Settings,StripePayments,PublicLiveReminders};
use App\Services\Publishing\{ConnectionStore,ConnectorRegistry};
class IntegrationWorkspaceController extends Controller
{
    public function index(ConnectionStore $store,ConnectorRegistry $registry,Settings $settings)
    {
        return response()->json(['connections'=>collect(array_keys($registry->all()))->map(fn($provider)=>['provider'=>$provider,'connected'=>$store->connected($provider),'public_url'=>$store->connection($provider)['public_url']??null]),
            'services'=>['stripe'=>app(StripePayments::class)->ready(),'openai'=>(bool)$settings->secret('ai_api_key'),'email'=>app(PublicLiveReminders::class)->mailReady()],
            'profile_only'=>['tiktok','linkedin'],'settings_url'=>'/desktop?open=settings']);
    }
}
