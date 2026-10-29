{{-- A page's description, link card and canonical address. The card's picture is the page's own
     (web/icons/og/<key>.png, tool/link_cards.py) or the landing's (og-landing.png), 1200 by 630.
     Whether search may index the page is the controller's header (SITE_INDEXING), not set here. --}}
<meta name="description" content="{{ $meta['description'] }}">
<meta property="og:site_name" content="{{ __('meta.brand') }}">
<meta property="og:title" content="{{ $meta['title'] }}">
<meta property="og:description" content="{{ $meta['description'] }}">
<meta property="og:url" content="{{ $meta['url'] }}">
<meta property="og:type" content="website">
<meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale()) }}">
<meta property="og:image" content="{{ \App\Support\Site\SitePages::image($meta['url']) }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="{{ rtrim(parse_url($meta['url'], PHP_URL_PATH) ?? '/', '/') === '' ? __('pages.landing.og_image_alt') : $meta['title'] }}">
<meta name="twitter:card" content="summary_large_image">
<link rel="canonical" href="{{ $meta['url'] }}">
