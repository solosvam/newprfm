<?php

namespace App\Http\Controllers\Backend\Product;

use App\Http\Controllers\Controller;
use App\Models\Product\ProductReview;
use Illuminate\Http\RedirectResponse;

class ReviewsController extends Controller
{
    public function index()
    {
        $pending = ProductReview::with(['product.brand', 'customer'])
            ->where('active', false)
            ->latest()
            ->paginate(20, ['*'], 'pending_page');

        $approved = ProductReview::with(['product.brand', 'customer'])
            ->where('active', true)
            ->latest()
            ->paginate(20, ['*'], 'approved_page');

        return view('backend.product.reviews.list', compact('pending', 'approved'));
    }

    public function approve(ProductReview $review): RedirectResponse
    {
        $review->update(['active' => true]);

        return back()->with('success', 'Rəy təsdiqləndi.');
    }

    public function destroy(ProductReview $review): RedirectResponse
    {
        $review->delete();

        return back()->with('success', 'Rəy silindi.');
    }
}
