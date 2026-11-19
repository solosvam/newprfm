<?php

namespace App\Http\Controllers\Backend\Product;

use App\Http\Controllers\Controller;
use App\Models\Product\ProductReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewsController extends Controller
{
    /** Rəylər: filtr — ətir (brend + ad, hər söz ayrıca) və reytinq; hər iki siyahıya (təsdiqlənməmiş / təsdiqlənmiş) tətbiq olunur */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q'));
        $rating = in_array((int) $request->query('rating'), [1, 2, 3, 4, 5], true) ? (int) $request->query('rating') : null;
        $words = array_values(array_filter(preg_split('/\s+/u', mb_substr($search, 0, 100))));

        $query = fn (bool $active) => ProductReview::with(['product.brand', 'product.type', 'product.genders', 'customer'])
            ->where('active', $active)
            ->when($rating, fn ($q) => $q->where('rating', $rating))
            ->when($words, fn ($q) => $q->whereHas('product', function ($product) use ($words) {
                foreach ($words as $word) {
                    $like = '%'.addcslashes($word, '%_\\').'%';
                    $product->where(fn ($w) => $w->where('products.name', 'like', $like)
                        ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', $like)));
                }
            }))
            ->latest();

        $pending = $query(false)->paginate(20, ['*'], 'pending_page')->withQueryString();
        $approved = $query(true)->paginate(20, ['*'], 'approved_page')->withQueryString();

        return view('backend.product.reviews.list', compact('pending', 'approved', 'search', 'rating'));
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
