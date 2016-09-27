@if ($post->attachments->count())
@spaceless
<ul class="post-attachments attachment-count-{{ $post->attachments->count() }} {{ $post->attachments->count() > 1 ? "attachments-multi" : "attachments-single" }}">
    @foreach ($post->attachments as $attachment)
    <li class="post-attachment">
        @if (!isset($catalog) || !$catalog)
        <div class="attachment-container">
            @if ($attachment->isDeleted())
            <figure class="attachment attachment-deleted">
                {!! $attachment->getThumbnailHTML($board) !!}
            </figure>
            @else
            <a class="attachment-link"
                target="_blank"
                href="{!! $attachment->getDownloadUrl($board) !!}"
                data-download-url="{!! $attachment->getDownloadUrl($board) !!}"
                data-thumb-url="{!! $attachment->getThumbnailUrl($board) !!}">
                <figure class="attachment attachment-type-{{ $attachment->guessExtension() }} {{ $attachment->getThumbnailClasses() }}" data-widget="lazyimg">
                    <figcaption class="attachment-details">
                        <p class="attachment-detail">
                            @if ($attachment->isSpoiler())
                            <span class="detail-item detail-filename filename-spoilers">@lang('board.field.spoilers')</span>
                            @else
                            <span class="detail-item detail-filename filename-cleartext" title="{{ $attachment->pivot->filename }}">{{ $attachment->getShortFilename() }}</span>
                            <span class="detail-item detail-close">&nbsp;[@lang('board.action.close')]</span>
                            @endif
                        </p>
                    </figcaption>

                    {!! $attachment->getThumbnailHTML($board) !!}
                </figure>
            </a>
            @endif
        </div>

        @if (!$attachment->isDeleted())
        <div class="attachment-action-group">
            <div class="post-action-bar action-bar-attachments">
                @set('attachmentActions', false)
                <div class="post-action-tab action-tab-actions" data-no-instant>
                    <span class="post-action-label post-action-open"><span class="post-action-text">@lang('board.action.open')</span></span>
                    <ul class="post-action-groups">
                        <li class="post-action-group">
                            <ul class="post-actions">
                                @set('attachmentActions', true)
                                @if ($attachment->isSpoiler())
                                <li class="post-action">
                                    <a href="{{ $attachment->getUnspoilerUrl($board) }}" target="_blank" class="post-action-link attachment-unspoiler" title="@lang('board.field.unspoiler')" data-no-instant>
                                        <i class="fa fa-question"></i>&nbsp;@lang('board.field.unspoiler')
                                    </a>
                                </li>
                                @else
                                <li class="post-action">
                                    <a href="{{ $attachment->getSpoilerUrl($board) }}" target="_blank" class="post-action-link attachment-spoiler" title="@lang('board.field.spoiler')" data-no-instant>
                                        <i class="fa fa-question"></i>&nbsp;@lang('board.field.spoiler')
                                    </a>
                                </li>
                                @endif

                                <li class="post-action">
                                    <a href="{{ $attachment->getRemoveUrl($board) }}" target="_blank" class="post-action-link attachment-remove" title="@lang('board.field.remove')" data-no-instant>
                                        <i class="fa fa-remove"></i>&nbsp;@lang('board.field.remove')
                                    </a>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>

            {{-- Note: Strict LTR direct here because this is technical info. --}}
            <a class="attachment-action attachment-download" dir="ltr" target="_blank"
                href="{!! $attachment->getDownloadUrl($board, true) !!}"
                download="{!! $attachment->getDownloadName() !!}"
            >
                <span class="detail-item detail-download">@lang('board.field.download')</span>
                <span class="detail-item detail-filesize">{{ $attachment->getHumanFilesize() }}</span>
                <span class="detail-item detail-filedim" title="{{ $attachment->getFileDimensions() }}">{{ $attachment->getFileDimensions() }}</span>
            </a>
        </div>
        @endif
        @else
        <a href="{!! $post->getUrl() !!}" data-instant>
            <figure class="attachment attachment-type-{{ $attachment->guessExtension() }}" data-widget="lazyimg">
                {!! $attachment->getThumbnailHTML($post->board, 150) !!}
            </figure>
        </a>
        @endif
    </li>
    @endforeach
</ul>
@endspaceless
@endif
