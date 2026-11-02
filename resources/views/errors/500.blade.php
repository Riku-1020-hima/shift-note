<x-error-page code="500" title="うまく表示できませんでした">
    <p>アプリの中で問題が起きました。あなたの操作のせいではありません。</p>

    <h2>こうすると直ります</h2>
    <ol>
        <li>少し待ってから、画面を<b>再読み込み</b>する</li>
        <li>それでも直らないときは、前の画面に戻って、別の操作を試す</li>
        <li>何度やっても同じときは、お店(またはアプリの管理者)に、この画面が出たことと、直前にした操作を伝えてください</li>
    </ol>

    <div class="actions">
        <button type="button" class="btn primary" onclick="location.reload()">再読み込みする</button>
        <a class="btn" href="{{ url('/') }}">ホームを開く</a>
    </div>
</x-error-page>
