{{-- The pages' top bar: the public site's header (components/site/header). --}}
<x-site.header :app-url="$links['app'] ?? \App\Support\Http\Hosts::app('/')" />
