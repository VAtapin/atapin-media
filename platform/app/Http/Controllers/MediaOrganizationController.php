<?php

namespace App\Http\Controllers;

use App\Services\MediaOrganization;
use Illuminate\Http\Request;

class MediaOrganizationController extends Controller
{
    public function update(Request $request, MediaOrganization $organization)
    {
        $data = $request->validate(['ids' => 'required|array|min:1|max:100', 'ids.*' => 'required|uuid|distinct',
            'status' => 'nullable|in:unsorted,ready,needs_attention', 'target_profile' => 'nullable|in:media_library,videos,shorts,posts',
            'add_tags' => 'nullable|array|max:30', 'add_tags.*' => 'required|string|max:100',
            'collection_id' => 'nullable|integer|exists:collections,id', 'archived' => 'sometimes|boolean']);
        return response()->json(['status' => 'saved', 'count' => $organization->apply($data)]);
    }
}
