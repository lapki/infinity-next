@extends('layouts.main.board')

@section('content')
<main class="board-index index-catalog">

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
        'showCatalog' => false,
        'showIndex'   => true,
        'showPages'   => false,
        'header'      => true,
    ])

    <section class="index-threads static">
        <ul class="thread-list">
            @foreach ($posts as $post)
            <li class="thread-item">
                <article class="thread">
                    @include('content.board.catalog'), [
                        'board'      => $board,
                        'post'       => $post,
                        'multiboard' => false,
                        'preview'    => false,
                    ])
                </article>
            </li>
            @endforeach
        </ul>
    </section>

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

    @include('content.board.sidebar')
</main>
@stop

@section('footer-inner')
    @include('nav.board.pages', [
        'showCatalog' => false,
        'showIndex'   => true,
        'showPages'   => true,
        'header'      => false,
    ])
@stop
