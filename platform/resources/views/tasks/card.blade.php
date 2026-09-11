<article class="task-card"><h3>{{ $task->title }}</h3>@if($task->description)<p class="muted prose">{{ $task->description }}</p>@endif
@if($task->project)<a class="task-project" href="{{ route('projects.show',$task->project) }}">{{ $task->project->title }}</a>@endif
<div class="task-meta"><span>{{ $task->assignee?->name ?? __('ui.unassigned') }}</span>@if($task->due_date)<time @class(['overdue'=>$task->due_date->isPast()&&!$task->due_date->isToday()&&$task->status!=='done'])>{{ $task->due_date->format('d.m.Y') }}</time>@endif</div>
<form class="task-status" method="post" action="{{ route('tasks.update',$task) }}">@csrf @method('PATCH')<label><span class="sr-only">{{ __('ui.status') }}</span><select name="status">@foreach(\App\Models\Task::STATES as $state)<option value="{{ $state }}" @selected($task->status===$state)>{{ __('ui.task_'.$state) }}</option>@endforeach</select></label><button class="button secondary">{{ __('ui.save') }}</button></form>
</article>
