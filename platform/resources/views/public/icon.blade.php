<svg width="24" height="24" viewBox="0 0 24 24" class="public-icon" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" stroke-linecap="round" aria-hidden="true">
@switch($name)
@case('community')<circle cx="12" cy="7" r="3" fill="currentColor"/><path d="M6 21v-3a6 6 0 0 1 12 0v3ZM3 5a2 2 0 0 1 0 4m18-4a2 2 0 0 0 0 4M2 19v-3a4 4 0 0 1 3-4m17 7v-3a4 4 0 0 0-3-4"/>@break
@case('video')<path d="m8 4 13 8-13 8Z"/>@break
@case('article')<path d="M5 2h10l5 5v15H5ZM14 2v6h6M8 12h9M8 16h9"/>@break
@case('book')<path d="M12 5v16M2 3c4-1 7 0 10 2 3-2 6-3 10-2v16c-4-1-7 0-10 2-3-2-6-3-10-2Z"/>@break
@case('live')<path d="M6 4a11 11 0 0 0 0 16M18 4a11 11 0 0 1 0 16M8 7a7 7 0 0 0 0 10m8-10a7 7 0 0 1 0 10M12 14v8"/><circle cx="12" cy="11" r="2" fill="currentColor"/>@break
@case('podcast')<rect x="8" y="2" width="8" height="13" rx="4"/><path d="M5 10v2a7 7 0 0 0 14 0v-2m-7 9v3m-4 0h8"/>@break
@case('newsletter')<path d="m3 10 19-8-6 20-5-8-8-4Zm8 4L22 2M11 14v7l3-3"/>@break
@endswitch
</svg>
