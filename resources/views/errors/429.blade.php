<x-error-page code="429" title="短い時間に操作が集中しました">
    <p>同じ操作が何度も続いたため、安全のため少しだけ止めています。</p>

    <h2>こうすると直ります</h2>
    <ol>
        <li><b>1分ほど待つ</b></li>
        <li>前の画面に戻って、もう一度やり直す</li>
    </ol>

    <div class="actions">
        <button type="button" class="btn primary" onclick="history.back()">前の画面に戻る</button>
    </div>
</x-error-page>
