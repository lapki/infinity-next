<section id="site-recent-images" class="grid-50 tablet-grid-50 mobile-grid-50 {{ (isset($rtl) && $rtl) ? 'push-50' : ''}}">
    <div class="smooth-box">
        <h2 class="index-title">@lang('index.title.recent_images')</h2>
        <ul class="recent-images selfclear">
            @foreach (\App\FileAttachment::getRecentImages(30, !$nsfw) as $file)
                @if ($file->storage->hasThumb())
                <li class="recent-image {{ $file->post->board->isWorksafe() ? 'sfw' : 'nsfw' }}">
                    <a class="recent-image-link" href="{{ $file->post->getURL() }}">
                        {!! $file->storage->getThumbnailHTML($file->post->board, 116) !!}
                    </a>
                </li>
                @endif
            @endforeach
        </ul>
    </div>
</section>
