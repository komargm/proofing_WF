(() => {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  async function post(url, data) {
    const body = new URLSearchParams(data || {});
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-CSRF-Token': csrf,
      },
      body,
      credentials: 'same-origin',
    });
    const json = await res.json().catch(() => null);
    if (!res.ok || !json || json.ok !== true) {
      const msg = (json && json.error) ? json.error : 'Wystąpił błąd.';
      throw new Error(msg);
    }
    return json;
  }

  function tile(el) {
    return el.closest('.photo-tile');
  }

  function setSelectedUI(t, selected) {
    const heart = t.querySelector('.heart');
    const pill = t.querySelector('.js-selected-pill');
    if (selected) {
      heart?.classList.add('is-on');
      pill?.classList.add('green');
      if (pill) pill.textContent = 'Wybrane';
    } else {
      heart?.classList.remove('is-on');
      pill?.classList.remove('green');
      if (pill) pill.textContent = 'Nie wybrane';
    }
  }

  function setRatingUI(t, rating) {
    const pill = t.querySelector('.js-rating-pill');
    const val = t.querySelector('.js-rating-val');
    const btns = t.querySelectorAll('.rate-btn');
    btns.forEach(b => b.classList.toggle('is-on', Number(b.dataset.rating) === Number(rating)));

    if (rating) {
      if (pill) pill.style.display = '';
      if (val) val.textContent = String(rating);
    } else {
      if (pill) pill.style.display = 'none';
      if (val) val.textContent = '0';
    }
  }

  function setLastCommentUI(t, text) {
    const box = t.querySelector('.js-last-comment');
    if (!box) return;
    box.textContent = text;
  }

  document.addEventListener('click', async (e) => {
    const target = e.target;

    // Heart
    if (target && target.classList.contains('heart')) {
      e.preventDefault();
      e.stopPropagation();
      const t = tile(target);
      const pid = t?.dataset.photoId;
      if (!pid) return;

      try {
        target.disabled = true;
        const json = await post(`/client/photo/${pid}/toggle-select`, {});
        setSelectedUI(t, json.selected);
      } catch (err) {
        alert(err.message || 'Błąd');
      } finally {
        target.disabled = false;
      }
    }

    // Rating set
    if (target && target.classList.contains('rate-btn')) {
      e.preventDefault();
      const t = tile(target);
      const pid = t?.dataset.photoId;
      const rating = target.dataset.rating;
      if (!pid || !rating) return;

      try {
        target.disabled = true;
        const json = await post(`/client/photo/${pid}/rate`, { rating });
        setRatingUI(t, json.rating);
      } catch (err) {
        alert(err.message || 'Błąd');
      } finally {
        target.disabled = false;
      }
    }

    // Rating clear
    if (target && target.classList.contains('rate-clear')) {
      e.preventDefault();
      const t = tile(target);
      const pid = t?.dataset.photoId;
      if (!pid) return;

      try {
        target.disabled = true;
        const json = await post(`/client/photo/${pid}/rate`, { rating: 0 });
        setRatingUI(t, json.rating);
      } catch (err) {
        alert(err.message || 'Błąd');
      } finally {
        target.disabled = false;
      }
    }
  });

  // Comment form submit (enter or button)
  document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (!form.classList.contains('comment-form')) return;

    e.preventDefault();
    const t = tile(form);
    const pid = t?.dataset.photoId;
    if (!pid) return;

    const input = form.querySelector('input[name="text"]');
    const text = input?.value || '';
    if (!text.trim()) return;

    const btn = form.querySelector('button[type="submit"]');
    try {
      if (btn) btn.disabled = true;
      const json = await post(`/client/photo/${pid}/comment`, { text });
      setLastCommentUI(t, json.comment.comment_text);
      if (input) input.value = '';
    } catch (err) {
      alert(err.message || 'Błąd');
    } finally {
      if (btn) btn.disabled = false;
    }
  });

  // ===== Viewer page actions (client/admin photo page) =====
  document.addEventListener('DOMContentLoaded', () => {
    const chat = document.getElementById('js-chat');
    if (chat) chat.scrollTop = chat.scrollHeight; // auto-scroll to bottom
  });

  // Client: heart toggle
  document.addEventListener('click', async (e) => {
    const heart = e.target?.closest?.('#js-heart');
    if (heart) {
      e.preventDefault();
      const pid = heart.getAttribute('data-photo-id');
      if (!pid) return;
      try {
        heart.disabled = true;
        const json = await post(`/client/photo/${pid}/toggle-select`, {});
        heart.classList.toggle('is-on', !!json.selected);
      } catch (err) {
        alert(err.message || 'Błąd');
      } finally {
        heart.disabled = false;
      }
    }

    // Client: rating buttons on viewer page
    const rateBtn = e.target?.closest?.('.rating-row .rate-btn');
    if (rateBtn) {
      e.preventDefault();
      const row = rateBtn.closest('.rating-row');
      const pid = row?.getAttribute('data-photo-id');
      const rating = rateBtn.getAttribute('data-rating');
      if (!pid || !rating) return;
      try {
        const json = await post(`/client/photo/${pid}/rate`, { rating });
        row.querySelectorAll('.rate-btn').forEach(b => b.classList.remove('is-on'));
        rateBtn.classList.add('is-on');
      } catch (err) {
        alert(err.message || 'Błąd');
      }
    }

    const clearBtn = e.target?.closest?.('.rating-row .rate-clear');
    if (clearBtn) {
      e.preventDefault();
      const row = clearBtn.closest('.rating-row');
      const pid = row?.getAttribute('data-photo-id');
      if (!pid) return;
      try {
        await post(`/client/photo/${pid}/rate`, { rating: 0 });
        row.querySelectorAll('.rate-btn').forEach(b => b.classList.remove('is-on'));
      } catch (err) {
        alert(err.message || 'Błąd');
      }
    }
  });

  // Client: chat submit (viewer)
  document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;

    if (form.id === 'js-chat-form') {
      e.preventDefault();
      const pid = form.getAttribute('data-photo-id');
      const input = form.querySelector('input[name="text"]');
      const text = input?.value || '';
      if (!pid || !text.trim()) return;

      const btn = form.querySelector('button[type="submit"]');
      try {
        if (btn) btn.disabled = true;
        const json = await post(`/client/photo/${pid}/comment`, { text });

        const chat = document.getElementById('js-chat');
        if (chat) {
          const wrap = document.createElement('div');
          wrap.className = 'chat-msg from-client';
          wrap.innerHTML = `
            <div class="chat-meta">
              <strong>Klient</strong>
              <span class="muted">${json.comment.created_at || ''}</span>
            </div>
            <div class="chat-text"></div>
          `;
          wrap.querySelector('.chat-text').textContent = json.comment.comment_text || text;
          chat.appendChild(wrap);
          chat.scrollTop = chat.scrollHeight;
        }

        if (input) input.value = '';
      } catch (err) {
        alert(err.message || 'Błąd');
      } finally {
        if (btn) btn.disabled = false;
      }
    }

    // Admin: chat submit (viewer)
    if (form.id === 'js-admin-chat-form') {
      e.preventDefault();
      const pid = form.getAttribute('data-photo-id');
      const input = form.querySelector('input[name="text"]');
      const text = input?.value || '';
      if (!pid || !text.trim()) return;

      const btn = form.querySelector('button[type="submit"]');
      try {
        if (btn) btn.disabled = true;
        const json = await post(`/admin/photo/${pid}/comment`, { text });

        const chat = document.getElementById('js-chat');
        if (chat) {
          const wrap = document.createElement('div');
          wrap.className = 'chat-msg from-admin';
          wrap.innerHTML = `
            <div class="chat-meta">
              <strong>Fotograf</strong>
              <span class="muted">${json.comment.created_at || ''}</span>
            </div>
            <div class="chat-text"></div>
          `;
          wrap.querySelector('.chat-text').textContent = json.comment.comment_text || text;
          chat.appendChild(wrap);
          chat.scrollTop = chat.scrollHeight;
        }

        if (input) input.value = '';
      } catch (err) {
        alert(err.message || 'Błąd');
      } finally {
        if (btn) btn.disabled = false;
      }
    }
  });

  // Admin: download toggle (viewer)
  document.addEventListener('change', async (e) => {
    const cb = e.target?.closest?.('#js-download-toggle');
    if (!cb) return;
    const pid = cb.getAttribute('data-photo-id');
    if (!pid) return;

    const allowed = cb.checked ? 1 : 0;
    try {
      cb.disabled = true;
      const json = await post(`/admin/photo/${pid}/download-allowed`, { allowed });
      const label = document.getElementById('js-download-label');
      if (label) label.textContent = json.allowed ? 'TAK' : 'NIE';
    } catch (err) {
      cb.checked = !cb.checked;
      alert(err.message || 'Błąd');
    } finally {
      cb.disabled = false;
    }
  });

  // Keyboard nav on viewer page
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
    const viewer = document.querySelector('.viewer');
    if (!viewer) return;

    const links = Array.from(document.querySelectorAll('.viewer-nav a.btn[href^="/client/photo/"], .viewer-nav a.btn[href^="/admin/photo/"]'));
    const prevLink = links.find(a => a.textContent.includes('Poprzednie'));
    const nextLink = links.find(a => a.textContent.includes('Następne'));

    if (e.key === 'ArrowLeft' && prevLink) window.location.href = prevLink.getAttribute('href');
    if (e.key === 'ArrowRight' && nextLink) window.location.href = nextLink.getAttribute('href');
  });

})();
