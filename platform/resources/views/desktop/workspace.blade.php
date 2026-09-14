<section class="desktop-workspace" data-workspace="{{ $app }}" data-can-edit="{{ auth()->user()->hasPermission('content.edit')?'true':'false' }}" data-can-projects="{{ auth()->user()->hasPermission('projects.manage')?'true':'false' }}" data-can-publish="{{ auth()->user()->hasPermission('content.publish')?'true':'false' }}">
    <header class="workspace-head"><div><p class="workspace-eyebrow">{{ __('workspaces.eyebrow') }}</p><h1>{{ __('workspaces.'.(in_array($app,['overview','polls'])?$app:'titles.'.$app)) }}</h1><p>{{ in_array($app,['overview','polls'])?'':__('workspaces.intros.'.$app) }}</p></div><div data-workspace-actions></div></header>
    <div class="workspace-feedback" data-workspace-feedback role="status" aria-live="polite"></div>
    <form class="workspace-filters" data-workspace-filters></form>
    <div class="workspace-layout"><section class="workspace-list" data-workspace-list></section><aside class="workspace-editor" data-workspace-editor></aside></div>
    <nav class="workspace-pager" data-workspace-pager aria-label="{{ __('workspaces.pages') }}"></nav>
</section>
