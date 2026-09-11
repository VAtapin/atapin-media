<?php
namespace Tests\Feature;
use App\Models\User;
use App\Models\Role;
use App\Models\Project;
use App\Models\Task;
use App\Services\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class WorkflowTest extends TestCase
{
    use RefreshDatabase;
    public function test_project_tasks_calendar_and_continue_working(): void
    {
        app(Access::class)->seed(); $user=User::factory()->create();
        $user->roles()->attach(Role::where('name','Editor')->firstOrFail()); $this->actingAs($user);
        $this->post('/desktop/projects',['title'=>'Hoffnung','description'=>'Eine neue Serie','status'=>'idea','due_date'=>'2026-10-10'])->assertRedirect();
        $project=Project::firstOrFail();
        $this->post('/desktop/tasks',['title'=>'Skript schreiben','status'=>'open','project_id'=>$project->id,'assigned_to'=>$user->id,'due_date'=>'2026-10-01'])->assertRedirect();
        $task=Task::firstOrFail();
        $this->patch('/desktop/tasks/'.$task->id,['status'=>'done'])->assertRedirect();
        $this->assertDatabaseHas('tasks',['id'=>$task->id,'status'=>'done','title'=>'Skript schreiben']);
        $this->get('/desktop/projects/'.$project->id)->assertOk()->assertSee('Skript schreiben');
        $this->get('/desktop/calendar?month=2026-10')->assertOk()->assertSee('Skript schreiben')->assertSee('Hoffnung');
        $this->get('/desktop/calendar?month=2026-11&view=list')->assertOk()->assertDontSee('Skript schreiben');
        $this->get('/desktop/tasks?view=mine')->assertOk()->assertSee('Skript schreiben');
        $this->get('/desktop/tasks')->assertOk()->assertSee('Skript schreiben');
        $this->get('/desktop')->assertOk()->assertSee('Hoffnung');
        $this->assertDatabaseHas('audit_events',['action'=>'task.saved','subject'=>(string)$task->id]);
    }
    public function test_workflow_denies_unprivileged_users_and_invalid_transitions(): void
    {
        app(Access::class)->seed();$user=User::factory()->create();$this->actingAs($user);
        $this->post('/desktop/projects',['title'=>'No'])->assertForbidden();
        $user->roles()->attach(Role::where('name','Editor')->firstOrFail());
        $this->post('/desktop/projects',['title'=>'Invalid','status'=>'invented'])->assertSessionHasErrors('status');
        $this->post('/desktop/tasks',['title'=>'Invalid','status'=>'open','project_id'=>999999])->assertSessionHasErrors('project_id');
        $this->get('/desktop/calendar?month=not-a-date')->assertSessionHasErrors('month');
        $this->assertDatabaseCount('projects',0);$this->assertDatabaseCount('tasks',0);
    }
}
