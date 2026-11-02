// 「友達にシェア」ダイアログ. Owner: Lin (FEAT-20)
// favorites.php と history.php で使う。callApi() / bootstrap は footer.php で読み込まれる。
// 使い方: openShareDialog({ favorite_id: 3 }, 'いつもの')
//         openShareDialog({ order_bowl_id: 7 }, '基本の麻辣湯')

let shareSource = null;   // { favorite_id } or { order_bowl_id }
let shareModal  = null;

function shareDialogHtml() {
  return `
  <div class="modal fade" id="shareDialog" tabindex="-1" aria-labelledby="shareDialogTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form class="modal-content" id="shareForm">
        <div class="modal-header">
          <h2 class="modal-title heading m-0" id="shareDialogTitle"><svg class="me-1" width="20" height="20"><use href="#i-users"></use></svg>友達にシェア</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
        </div>
        <div class="modal-body">
          <p class="caption" id="shareWhat"></p>
          <div id="shareError" class="alert alert-danger d-none"></div>
          <div id="shareDone" class="alert alert-success d-none"></div>
          <div id="shareFields">
            <div class="field">
              <label class="field-label" for="shareFriend">送る相手</label>
              <select class="input" id="shareFriend" required></select>
            </div>
            <div class="field">
              <label class="field-label" for="shareMessage">メッセージ（なくてもOK）</label>
              <textarea class="input" id="shareMessage" maxlength="200" rows="2" placeholder="例: これ絶対おいしいから食べてみて！"></textarea>
            </div>
          </div>
          <div id="shareNoFriends" class="d-none">
            <p>まだ友達がいません。先に友達を追加してください。</p>
            <a class="btn btn-outline btn-sm" href="friends.php#friends">友達を追加する</a>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline btn-sm" data-bs-dismiss="modal">閉じる</button>
          <button type="submit" class="btn btn-chili btn-sm" id="shareSend">シェアする</button>
        </div>
      </form>
    </div>
  </div>`;
}

function setupShareDialog() {
  document.body.insertAdjacentHTML('beforeend', shareDialogHtml());
  shareModal = new bootstrap.Modal(document.getElementById('shareDialog'));

  document.getElementById('shareForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const send = document.getElementById('shareSend');
    document.getElementById('shareError').classList.add('d-none');
    send.disabled = true;
    const res = await callApi('POST', '../api/shares.php', {
      ...shareSource,
      friend_id: Number(document.getElementById('shareFriend').value),
      message: document.getElementById('shareMessage').value,
    });
    send.disabled = false;
    if (!res.success) {
      showError(document.getElementById('shareError'), res.error);
      return;
    }
    const to = document.getElementById('shareFriend').selectedOptions[0].dataset.name;
    const done = document.getElementById('shareDone');
    done.textContent = `${to} さんに送りました。`;   // 名前は textContent (HTML として読まない)
    done.insertAdjacentHTML('beforeend', ' <a href="friends.php#sent">送ったレシピを見る →</a>');
    done.classList.remove('d-none');
    document.getElementById('shareFields').classList.add('d-none');
    send.classList.add('d-none');
  });
}

async function openShareDialog(source, label) {
  if (!shareModal) setupShareDialog();
  shareSource = source;

  // 前の結果を消して、最初の状態にもどす
  document.getElementById('shareWhat').textContent = label ? `「${label}」を送ります。届いた相手は同じ内容でそのまま注文できます。` : '';
  document.getElementById('shareError').classList.add('d-none');
  document.getElementById('shareDone').classList.add('d-none');
  document.getElementById('shareMessage').value = '';
  const select = document.getElementById('shareFriend');
  select.innerHTML = '<option>読み込み中…</option>';
  shareModal.show();

  const res = await callApi('GET', '../api/friends.php');
  if (!res.success) {
    showError(document.getElementById('shareError'), res.error);
    return;
  }
  const hasFriends = res.data.length > 0;
  document.getElementById('shareFields').classList.toggle('d-none', !hasFriends);
  document.getElementById('shareNoFriends').classList.toggle('d-none', hasFriends);
  document.getElementById('shareSend').classList.toggle('d-none', !hasFriends);

  select.innerHTML = '';
  for (const f of res.data) {
    const option = document.createElement('option');
    option.value = f.friend_id;
    option.dataset.name = f.nickname || f.name;
    option.textContent = (f.nickname || f.name) + (f.relation === 'family' ? '（家族）' : '');
    select.appendChild(option);
  }
}
