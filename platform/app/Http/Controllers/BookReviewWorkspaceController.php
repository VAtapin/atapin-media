<?php

namespace App\Http\Controllers;

use App\Models\BookReview;
use App\Services\Audit;
use Illuminate\Http\Request;

class BookReviewWorkspaceController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:120',
            'status' => 'nullable|in:pending,published,rejected',
            'page' => 'nullable|integer|min:1',
        ]);

        $page = BookReview::query()
            ->with(['user:id,name', 'product:id,title'])
            ->when($data['status'] ?? '', fn ($query, $status) => $query->where('status', $status))
            ->when($data['q'] ?? '', function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('body', 'like', '%'.$search.'%')
                        ->orWhereHas('user', fn ($users) => $users->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('product', fn ($products) => $products->where('title', 'like', '%'.$search.'%'));
                });
            })
            ->latest()
            ->paginate(30);

        return response()->json($page)->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, BookReview $review, Audit $audit)
    {
        $data = $request->validate(['status' => 'required|in:published,rejected']);
        $review->update($data);
        $audit->record('shop.review_moderated', (string) $review->id);

        if (! $request->expectsJson()) {
            return back()->with('public_status', __('public.saved'));
        }

        return response()->json([
            'id' => $review->id,
            'status' => $review->status,
            'message' => __('book-reviews.saved'),
        ]);
    }
}
