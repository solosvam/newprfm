                    @php
                        $image = $product->images->first();
                        $gender = $product->genders->first();
                        $variants = $product->variants->where('active', 1);
                        $firstVariant = $variants->first();
                        $locale = app()->getLocale();

                        $genderName = $gender
                            ? ($gender->{'name_' . $locale} ?? $gender->name_az)
                            : null;

                        $typeName = $product->type
                            ? ($product->type->{'name_' . $locale} ?? $product->type->name_az)
                            : null;
                    @endphp
                    <div class="card" data-href="{{ route('product', $product->slug) }}">
                        <div class="thumb">
                            <div class="thumb-actions">
                                @include('frontend.includes.favorite-button', ['product' => $product, 'selected' => false])

                                <button
                                    type="button"
                                    class="icon-btn share-btn"
                                    data-url="{{ route('product', $product->slug) }}"
                                    data-title="{{ $product->name }}"
                                    aria-label="{{ __('common_share') }}"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5 15.4 17.5M15.4 6.5 8.6 10.5"/></svg>
                                </button>
                            </div>

                            @if ($image)
                                <img
                                    src="{{ asset('frontend/uploads/products/' . $image->image) }}"
                                    alt="{{ $product->name }}"
                                    loading="lazy"
                                >
                            @else
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="34" height="34"><path d="M9 3h6l1 4H8l1-4Z"/><path d="M8 7h8l1 13a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L8 7Z"/></svg>
                            @endif
                        </div>

                        <p class="brandname">{{ $product->brand?->name }} ·
                            @if($genderName || $typeName)
                                <span class="product-type">
                                    @if($genderName)
                                        {{ $genderName }}
                                    @endif

                                    @if($genderName && $typeName)
                                        |
                                    @endif

                                    @if($typeName)
                                        {{ $typeName }}
                                    @endif
                                </span>
                            @endif
                        </p>
                        <p class="pname">{{ $product->name }}</p>
                        <p class="price">@if($firstVariant){{ $firstVariant->size?->{'name_' . $locale} ?? $firstVariant->size?->name_az }} / {{ number_format((float) $firstVariant->price, 2) }} ₼@endif</p>
                    </div>
