@extends('layouts.main.board')

@section('content')
<main class="board-index index-catalog">
	<section class="index-form">
		@include( $c->template('board.post.form'), [
			'board'   => $board,
			'actions' => [ $reply_to ? "reply" : "thread" ],
		])
	</section>
	
	<section class="index-threads static">
		<ul class="thread-list">
			@foreach ($posts as $thread)
			<li class="thread-item">
				<article class="thread">
					@include($c->template('board.catalog'), [
						'board'   => $board,
						'thread'  => $thread,
					])
				</article>
			</li>
			@endforeach
		</ul>
	</section>
</main>
@stop