                    @php
                        $image = $product->images->first();
                        // Hover-də ikinci şəkil: yalnız ünvan yazılır, main.js ilk hover-də yükləyir (mobildə heç vaxt)
                        $hoverImage = $product->images->skip(1)->first();
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
                    <div class="card {{ $product->visibleDiscount() ? 'card--sale' : '' }}" data-href="{{ route('product', $product->slug) }}">
                        @include('frontend.partials.discount-badge', ['product' => $product])
                        <div class="thumb" @if($image && $hoverImage) data-hover-src="{{ route('product.image', ['size' => 400, 'image' => $hoverImage->image]) }}" @endif>
                            {{-- Endirimin bitməsinə 7 gündən az qalıb: şəklin altında canlı geri sayım (main.js → [data-card-countdown]);
                                 hover-də ölçülər siyahısı onun üstünü örtür --}}
                            @if(($cardDiscount = $product->visibleDiscount()) && $cardDiscount->showsCardCountdown())
                                <div class="card-countdown" data-card-countdown="{{ $cardDiscount->ends_at->getTimestampMs() }}">
                                    {{-- nəyin sayıldığı aydın olsun --}}
                                    <span class="card-countdown__caption">{{ __('discount_ends_in') }}</span>
                                    <span class="card-countdown__cells">
                                    @foreach(['d' => __('countdown_days'), 'h' => __('countdown_hours'), 'm' => __('countdown_minutes'), 's' => __('countdown_seconds')] as $unit => $label)
                                        <span class="card-countdown__cell"><b data-unit="{{ $unit }}">00</b><small>{{ $label }}</small></span>
                                    @endforeach
                                    </span>
                                </div>
                            @endif
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
                                {{-- 200px — 1x ekranlar və kiçik kartlar, 400px — retina (ProductImageController ölçüləri) --}}
                                <img
                                    src="{{ route('product.image', ['size' => 400, 'image' => $image->image]) }}"
                                    srcset="{{ route('product.image', ['size' => 200, 'image' => $image->image]) }} 200w, {{ route('product.image', ['size' => 400, 'image' => $image->image]) }} 400w"
                                    sizes="(max-width: 760px) 40vw, 240px"
                                    alt="{{ $product->name }}"
                                    width="400"
                                    height="400"
                                    decoding="async"
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

                        <div class="price-wrap">
                            <p class="price">@if($firstVariant){{ $firstVariant->size?->{'name_' . $locale} ?? $firstVariant->size?->name_az }} / @include('frontend.partials.sale-price', ['variant' => $firstVariant])@endif</p>

                            @if($variants->count() > 1)
                                <div class="price-all">
                                    @foreach($variants as $variant)
                                        <div class="price-all__row">
                                            <span>{{ $variant->size?->{'name_' . $locale} ?? $variant->size?->name_az }}</span>
                                            <strong>@include('frontend.partials.sale-price', ['variant' => $variant])</strong>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
