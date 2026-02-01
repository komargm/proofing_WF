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
    btns.forEach(b => {
      const r = Number(rating || 0);
      const v = Number(b.dataset.rating || 0);
      b.classList.toggle('is-on', r > 0 && v <= r);
    });

    if (rating) {
      if (pill) pill.style.display = '';
      if (val) val.textContent = String(rating);
    } else {
      if (pill) pill.style.display = 'none';
      if (val) val.textContent = '0';
    }

    // Wspólna ocena (client>=2 && admin>=2) – tylko hint, bez pokazywania admin_rating
    const matchPill = t.querySelector('.js-match-pill');
    if (matchPill) {
      const ar = Number(t.dataset.adminRating || 0);
      const cr = Number(rating || 0);
      matchPill.style.display = (ar >= 2 && cr >= 2) ? '' : 'none';
      const name = t.dataset.photographerName || '';
      if (name) matchPill.textContent = `👍 ${name} też lubi`;
    }
  }

  
  function setAdminRatingUI(container, rating) {
    const btns = container?.querySelectorAll?.('.admin-rate-btn') || [];
    btns.forEach(b => {
      const r = Number(rating || 0);
      const v = Number(b.dataset.rating || 0);
      b.classList.toggle('is-on', r > 0 && v <= r);
    });
  }

function setLastCommentUI(t, text) {
    const box = t.querySelector('.js-last-comment');
    if (!box) return;
    box.textContent = text;
  }

  document.addEventListener('click', async (e) => {
    const target = e.target;

    // Admin: rating set (tile or viewer)
    if (target && target.classList.contains('admin-rate-btn')) {
      e.preventDefault();
      const row = target.closest('.admin-rating-row');
      const pid = row?.getAttribute('data-photo-id') || tile(target)?.dataset.photoId;
      const rating = target.dataset.rating;
      if (!pid || !rating) return;
      try {
        target.disabled = true;
        const json = await post(`/admin/photo/${pid}/rate`, { rating });
        // UI: buttons
        setAdminRatingUI((row || tile(target)), Number(json.rating ?? rating));
        // UI: pill
        const pill = document.getElementById('js-admin-rating-pill') || (tile(target)?.querySelector('.js-admin-rating-pill'));
        if (pill) {
          if (json.rating) {
            pill.style.display = '';
            pill.textContent = `Twoja ocena: ★ ${json.rating}/6`;
          } else {
            pill.style.display = 'none';
          }
        }
      } catch (err) {
        alert(err.message || 'Błąd');
      } finally {
        target.disabled = false;
      }

      // IMPORTANT: admin buttons also have class "rate-btn".
      // Stop here to avoid triggering the client rating handler below.
      return;
    }

    // Admin: rating clear
    if (target && target.classList.contains('admin-rate-clear')) {
      e.preventDefault();
      const row = target.closest('.admin-rating-row');
      const pid = row?.getAttribute('data-photo-id') || tile(target)?.dataset.photoId;
      if (!pid) return;
      try {
        target.disabled = true;
        const json = await post(`/admin/photo/${pid}/rate`, { rating: 0 });
        (row || tile(target))?.querySelectorAll('.admin-rate-btn').forEach(b => b.classList.remove('is-on'));
        const pill = document.getElementById('js-admin-rating-pill') || (tile(target)?.querySelector('.js-admin-rating-pill'));
        if (pill) pill.style.display = 'none';
      } catch (err) {
        alert(err.message || 'Błąd');
      } finally {
        target.disabled = false;
      }

      // Avoid falling-through into the client rating handler.
      return;
    }

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

      // Prevent falling through to client handlers.
      return;
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
        // Sync match metadata returned by backend (fresh admin_rating + imię fotografa)
        if (json && typeof json.admin_rating !== 'undefined') t.dataset.adminRating = String(json.admin_rating || 0);
        if (json && typeof json.photographer_first_name !== 'undefined') t.dataset.photographerName = String(json.photographer_first_name || '');
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
        if (json && typeof json.admin_rating !== 'undefined') t.dataset.adminRating = String(json.admin_rating || 0);
        if (json && typeof json.photographer_first_name !== 'undefined') t.dataset.photographerName = String(json.photographer_first_name || '');
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

    // Ujednolicenie UI gwiazdek po renderze z PHP (PHP zaznaczało tylko 1 gwiazdkę)
    document.querySelectorAll('.rating-row').forEach(row => {
      // Admin rating rows
      if (row.classList.contains('admin-rating-row') || row.querySelector('.admin-rate-btn')) {
        const on = row.querySelector('.admin-rate-btn.is-on');
        const r = Number(on?.dataset?.rating || 0);
        setAdminRatingUI(row, r);
      } else {
        const on = row.querySelector('.rate-btn.is-on');
        const r = Number(on?.dataset?.rating || 0);
        setRatingUI(row, r);
      }
    });
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
    // NOTE: admin viewer uses similar markup for admin_rating; ignore those controls here.
    const rateBtn = e.target?.closest?.('.rating-row .rate-btn');
    if (rateBtn) {
      if (rateBtn.classList.contains('admin-rate-btn') || rateBtn.closest('.admin-rating-row')) {
        return;
      }
      e.preventDefault();
      const row = rateBtn.closest('.rating-row');
      const pid = row?.getAttribute('data-photo-id');
      const rating = rateBtn.getAttribute('data-rating');
      if (!pid || !rating) return;
      try {
        const json = await post(`/client/photo/${pid}/rate`, { rating });
        setRatingUI(row, Number(json.rating ?? rating));

        const match = document.getElementById('js-match-pill');
        if (row) {
          // Update metadata from backend (works even if admin rated while page was open)
          if (typeof json.admin_rating !== 'undefined') row.setAttribute('data-admin-rating', String(json.admin_rating || 0));
          if (typeof json.photographer_first_name !== 'undefined') row.setAttribute('data-photographer-name', String(json.photographer_first_name || ''));
        }

        if (match) {
          const show = !!(json && json.match);
          match.style.display = show ? '' : 'none';
          const name = (json && json.photographer_first_name) ? String(json.photographer_first_name) : (row?.getAttribute('data-photographer-name') || '');
          if (name) match.textContent = `👍 ${name} też lubi`;
        }
      } catch (err) {
        alert(err.message || 'Błąd');
      }
    }

    const clearBtn = e.target?.closest?.('.rating-row .rate-clear');
    if (clearBtn) {
      if (clearBtn.classList.contains('admin-rate-clear') || clearBtn.closest('.admin-rating-row')) {
        return;
      }
      e.preventDefault();
      const row = clearBtn.closest('.rating-row');
      const pid = row?.getAttribute('data-photo-id');
      if (!pid) return;
      try {
        const json = await post(`/client/photo/${pid}/rate`, { rating: 0 });
        row.querySelectorAll('.rate-btn').forEach(b => b.classList.remove('is-on'));

        const match = document.getElementById('js-match-pill');
        if (row) {
          if (typeof json.admin_rating !== 'undefined') row.setAttribute('data-admin-rating', String(json.admin_rating || 0));
          if (typeof json.photographer_first_name !== 'undefined') row.setAttribute('data-photographer-name', String(json.photographer_first_name || ''));
        }

        if (match) {
          const show = !!(json && json.match);
          match.style.display = show ? '' : 'none';
          if (show) {
            const name = (json && json.photographer_first_name) ? String(json.photographer_first_name) : (row?.getAttribute('data-photographer-name') || '');
            if (name) match.textContent = `👍 ${name} też lubi`;
          }
        }
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
          const name = (json.comment && json.comment.author_name) ? json.comment.author_name : '';
          wrap.innerHTML = `
            <div class="chat-meta">
              <strong>${name}</strong>
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
          const name = (json.comment && json.comment.author_name) ? json.comment.author_name : '';
          wrap.innerHTML = `
            <div class="chat-meta">
              <strong>${name}</strong>
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
