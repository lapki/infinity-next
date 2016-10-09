@extends('layouts.main.board')

@section('body-class')@parent {{ $reply_to ? 'single-thread' : 'board-index' }}@endsection

@section('content')
<main class="board-index index-threaded mode-{{ $reply_to ? "reply" : "index" }} @if (isset($page)) page-{{ $page }} @endif">

    <section class="index-form">
        @if (config('cache.esi', false))
            <esi:include src="/.internal/site/post-form?board_uri={{ $board->board_uri }}&reply_to={{ $reply_to ? $reply_to->post_id : 0 }}">
        @else
            @include('content.board.post.form', [
                'board'   => $board,
                'actions' => [ $reply_to ? "reply" : "thread" ],
            ])
        @endif
    </section>

    @include('nav.board.pages', [
        'showCatalog' => true,
        'showIndex'   => !!$reply_to,
        'showPages'   => false,
        'header'      => true,
    ])

    <section class="index-threads">
        @include( 'widgets.ads.board_top_left' )

        <ul class="thread-list">
            @foreach ($posts as $thread)
            <li class="thread-item">
                <article class="thread">
                    <div class="thread-interior">
                        @include('content.board.thread', [
                            'board'   => $board,
                            'thread'  => $thread,
                        ])
                    </div>
                </article>
            </li>
            @endforeach
        </ul>
    </section>

    @include('content.board.sidebar')
</main>
@stop

@section('footer-inner')
    @include('nav.board.pages', [
        'showCatalog' => true,
        'showIndex'   => !!$reply_to,
        'showPages'   => true,
        'header'      => false,
    ])
@stop
