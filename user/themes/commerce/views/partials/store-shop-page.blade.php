<div class="store-arena store-arena--shop">
    <header class="store-page-head">
        <h1>{{ $page->title() }}</h1>
        <p class="store-page-deck">Featured products from your flat-file catalog. Use the cart menu for a quick peek.</p>
    </header>

    @include('partials.store-product-grid')

    <div class="store-page-prose page-content store-page-prose--secondary">
        {!! $page->content() !!}
    </div>
</div>
