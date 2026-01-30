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
})();
