<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Gate;

class DesktopWorkspaceController extends Controller
{
    public const APPS = ['overview'=>'desktop.view','polls'=>'content.edit','projects'=>'projects.manage','tasks'=>'projects.manage','calendar'=>'desktop.view',
        'books-pdf'=>'shop.manage','topics'=>'content.edit','series'=>'content.edit','newsletter'=>'subscribers.manage',
        'ai-assistant'=>'content.edit','analytics'=>'analytics.view','shop'=>'shop.manage','integrations'=>'integrations.manage','community'=>'community.moderate'];

    public function __invoke(string $app)
    {
        abort_unless(isset(self::APPS[$app]),404);
        Gate::authorize(self::APPS[$app]);
        if ($app === 'calendar') abort_unless(Gate::allows('projects.manage') || Gate::allows('content.publish'),403);
        return view('desktop.workspace',['app'=>$app]);
    }
}
