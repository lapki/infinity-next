<div class="grid-container">
    @include('widgets.messages')

    @include('content.index.sections.featured_boards')

    @include('content.index.sections.featured_post')

    @if (config('cache.esi', false))
        <esi:include src="/.internal/site/recent-images{{ $nsfw ? '?nsfw' : ''}}" />
        <esi:include src="/.internal/site/recent-posts" />
    @else
        @include('content.index.sections.recent_images')
        @include('content.index.sections.recent_posts')
    @endif
</div>
