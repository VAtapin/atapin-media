<?php
namespace App\Http\Controllers;
use App\Models\Media;
use App\Models\AuditEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
class DesktopController extends Controller
{
    public function __invoke()
    {
        $canMedia = Gate::allows('media.view');
        return view('desktop', [
            'project'=>Gate::allows('projects.manage') ? \App\Models\Project::where('status','!=','published')->latest('updated_at')->first() : null,
            'media' => $canMedia ? Media::latest()->limit(6)->get() : collect(),
            'count' => $canMedia ? Media::count() : null,
            'bytes' => $canMedia ? Media::sum('bytes') : null,
            'queued' => Gate::allows('settings.manage') ? DB::table('jobs')->count() : null,
            'failed' => Gate::allows('settings.manage') ? DB::table('failed_jobs')->count() : null,
        ]);
    }
    public function audit() { return view('audit', ['events' => AuditEvent::latest('id')->paginate(40)]); }
}
