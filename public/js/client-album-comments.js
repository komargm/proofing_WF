/**
 * PATCH 5.2 – komentarz pod miniaturą
 * Uwaga: wysyła na istniejący endpoint:
 *   POST /client/photo/{id}/comment
 *
 * Jeśli backend oczekuje innej nazwy pola niż "comment_text"
 * (np. "comment" albo "text"), zmień w fd.append(...) niżej.
 */
document.addEventListener('submit', async (e) => {
  const form = e.target;
  if (!form.classList.contains('comment-form')) return;

  e.preventDefault();

  const photoId = form.getAttribute('data-photo-id');
  const input = form.querySelector('input[name="comment_text"]');
  const text = (input.value || '').trim();
  if (!text) return;

  const fd = new FormData();
  fd.append('comment_text', text);

  const btn = form.querySelector('button[type="submit"]');
  if (btn) btn.disabled = true;

  try {
    const res = await fetch(`/client/photo/${photoId}/comment`, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin'
    });

    // część backendów zwraca JSON, część redirect/tekst – obsłużmy oba
    let ok = res.ok;
    let commentValue = text;

    const ct = (res.headers.get('content-type') || '').toLowerCase();
    if (ct.includes('application/json')) {
      const json = await res.json();
      ok = !!json.ok || res.ok;
      // jeśli backend zwraca treść komentarza w JSONie, podmień:
      if (json?.data?.comment_text) commentValue = json.data.comment_text;
      if (json?.comment_text) commentValue = json.comment_text;
    } else {
      // jeżeli backend robi redirect, to fetch dostanie 200 po przekierowaniu lub 302 (zależnie od konfiguracji)
      // nic – i tak w UI odświeżymy snippet lokalnie
    }

    if (!ok) {
      alert('Nie udało się dodać komentarza.');
      return;
    }

    const box = document.getElementById(`last-comment-${photoId}`);
    if (box) {
      box.innerHTML = `<span class="snippet">${escapeHtml(commentValue)}</span>`;
    }

    input.value = '';
  } finally {
    if (btn) btn.disabled = false;
  }
});

function escapeHtml(str) {
  return String(str)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}
