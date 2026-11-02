<x-error-page code="419" title="画面の有効期限が切れました">
    <p>安全のため、しばらく開いたままだった画面からは保存できないようになっています。</p>

    <h2>こうすると直ります</h2>
    <ol>
        <li>下の「前の画面に戻る」を押す</li>
        <li>戻った画面を<b>再読み込み</b>する(スマホは画面を下に引っぱる、パソコンは ⌘+R / F5)</li>
        <li>もう一度、保存や送信をする</li>
    </ol>
    <p>ログイン画面になったときは、もう一度ログインしてからやり直してください。</p>

    <div class="note">
        <b>よくある原因</b>:画面を長い時間開いたままにしていた/別のタブでログアウト・ログインした。<br>
        入力していた内容は、戻った画面に残っていることがあります。消えていたら、お手数ですが入力し直してください。
    </div>

    <div class="actions">
        <button type="button" class="btn primary" onclick="history.length > 1 ? history.back() : location.assign('{{ url('/') }}')">前の画面に戻る</button>
        <a class="btn" href="{{ url('/') }}">ホームを開く</a>
    </div>
</x-error-page>
