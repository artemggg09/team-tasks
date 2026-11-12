/*
 * TeamTasks: прогрессивное улучшение. Без этого файла приложение работает полностью.
 */
(function () {
  'use strict';

  document.documentElement.classList.add('js');

  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var desktop = window.matchMedia('(min-width: 1024px)');

  /* ---------- Тосты ---------- */
  var toasts = document.getElementById('toasts');
  function toast(message, isError) {
    if (!toasts) return;
    var el = document.createElement('div');
    el.className = 'toast' + (isError ? ' toast--error' : '');
    el.setAttribute('role', isError ? 'alert' : 'status');
    el.textContent = message;
    toasts.appendChild(el);
    setTimeout(function () { el.remove(); }, 3200);
  }

  /* ---------- Флеш-сообщения: кнопка закрытия и автоскрытие ---------- */
  document.querySelectorAll('.flash').forEach(function (flash) {
    var close = flash.querySelector('.flash__close');
    var hide = function () {
      flash.classList.add('is-leaving');
      setTimeout(function () { flash.remove(); }, 300);
    };
    if (close) {
      close.hidden = false;
      close.addEventListener('click', hide);
    }
    if (!flash.classList.contains('flash--error')) setTimeout(hide, 5000);
  });

  /* ---------- Фильтры: на ПК всегда раскрыты, изменения применяются сразу ---------- */
  var filters = document.getElementById('filters');
  var filterForm = document.getElementById('filter-form');
  function syncFilters() {
    if (filters && desktop.matches) filters.open = true;
  }
  syncFilters();
  if (desktop.addEventListener) desktop.addEventListener('change', syncFilters);

  if (filterForm) {
    filterForm.addEventListener('change', function (e) {
      if (e.target.matches('select, input[type="checkbox"]')) submitForm(filterForm);
    });
  }
  function submitForm(form) {
    if (form.requestSubmit) form.requestSubmit(); else form.submit();
  }

  /* ---------- Подтверждение удаления: закрывать другие и по клику вне ---------- */
  document.addEventListener('toggle', function (e) {
    var d = e.target;
    if (!d.classList || !d.classList.contains('confirm') || !d.open) return;
    document.querySelectorAll('details.confirm[open]').forEach(function (other) {
      if (other !== d) other.open = false;
    });
  }, true);
  document.addEventListener('click', function (e) {
    document.querySelectorAll('details.confirm[open]').forEach(function (d) {
      if (!d.contains(e.target)) d.open = false;
    });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('details.confirm[open]').forEach(function (d) {
      d.open = false;
      var s = d.querySelector('summary');
      if (s) s.focus();
    });
  });

  /* ---------- Быстрая смена статуса через fetch ---------- */
  var labels = { new: 'Новая', in_progress: 'В работе', done: 'Завершена' };

  document.addEventListener('change', function (e) {
    var select = e.target.closest ? e.target.closest('.status-select') : null;
    if (!select) return;
    changeStatus(select.form, select.value);
  });

  // Без JS форма отправляется кнопкой, с JS — перехватываем на всякий случай
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.classList.contains('status-form')) return;
    e.preventDefault();
    changeStatus(form, form.elements.status.value);
  });

  function changeStatus(form, status) {
    var card = form.closest('.task-card');
    var select = form.elements.status;
    var previous = card ? card.dataset.status : null;
    if (previous === status) return;

    var data = new FormData(form);
    data.set('status', status);
    select.value = status;
    select.disabled = true;

    fetch(form.action, {
      method: 'POST',
      body: data,
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf }
    })
      .then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (json) {
          if (!res.ok || !json.ok) throw new Error(json.error || 'Не удалось сменить статус');
          return json;
        });
      })
      .then(function (json) {
        if (card) applyStatus(card, json.task);
        updateCounts(json.counts);
        updatePace(json.pace);
        toast(json.message || ('Статус: ' + labels[status]));
      })
      .catch(function (err) {
        if (previous) select.value = previous;
        toast(err.message || 'Ошибка сети', true);
      })
      .finally(function () {
        select.disabled = false;
      });
  }

  function applyStatus(card, task) {
    Object.keys(labels).forEach(function (s) { card.classList.remove('status-' + s); });
    card.classList.add('status-' + task.status);
    card.classList.toggle('is-overdue', !!task.overdue);
    card.dataset.status = task.status;

    var select = card.querySelector('.status-select');
    if (select) select.value = task.status;

    var due = card.querySelector('.due');
    if (due) {
      due.classList.toggle('due--overdue', !!task.overdue);
      var label = due.querySelector('.due__label');
      if (label) label.textContent = task.overdue ? '· просрочена' : '';
    }

    // В канбане переносим карточку в нужную колонку
    var target = document.querySelector('.board__list[data-status="' + task.status + '"]');
    if (target && card.parentElement !== target) {
      target.insertBefore(card, target.firstChild);
      updateBoardCounts();
    }

    card.classList.remove('is-updated');
    void card.offsetWidth; // перезапуск анимации
    card.classList.add('is-updated');
  }

  function updateCounts(counts) {
    if (!counts) return;
    Object.keys(counts).forEach(function (key) {
      var el = document.querySelector('[data-count="' + key + '"]');
      if (el) el.textContent = counts[key];
    });
  }

  function updatePace(p) {
    var el = document.getElementById('pace');
    if (!el || !p) return;
    el.className = 'pace pace--' + p.level;
    el.querySelectorAll('.pace__seg').forEach(function (seg, i) {
      seg.classList.toggle('is-on', i < p.filled);
    });
    var ratio = el.querySelector('[data-pace="ratio"]');
    if (ratio) ratio.textContent = p.done + ' из ' + p.planned;
    var bar = el.querySelector('[data-pace="bar"]');
    if (bar) {
      bar.setAttribute('aria-valuenow', p.done);
      bar.setAttribute('aria-valuemax', p.planned);
    }
    var note = el.querySelector('[data-pace="note"]');
    if (note) note.className = 'pace__note pace__note--' + p.note_level;
    var text = el.querySelector('[data-pace="note-text"]');
    if (text) text.textContent = p.note;
  }

  function updateBoardCounts() {
    document.querySelectorAll('.board__col').forEach(function (col) {
      var n = col.querySelectorAll('.task-card').length;
      var el = col.querySelector('.board__count');
      if (el) el.textContent = n;
    });
  }

  /* ---------- Канбан: перетаскивание между колонками ---------- */
  var board = document.getElementById('board');
  if (board) {
    var dragged = null;

    board.querySelectorAll('.task-card').forEach(function (card) {
      card.setAttribute('draggable', 'true');
    });

    board.addEventListener('dragstart', function (e) {
      var card = e.target.closest && e.target.closest('.task-card');
      if (!card) return;
      dragged = card;
      card.classList.add('is-dragging');
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', card.dataset.id);
    });

    board.addEventListener('dragend', function () {
      if (dragged) dragged.classList.remove('is-dragging');
      dragged = null;
      board.querySelectorAll('.is-drop-target').forEach(function (l) { l.classList.remove('is-drop-target'); });
    });

    board.addEventListener('dragover', function (e) {
      var list = e.target.closest && e.target.closest('.board__list');
      if (!list || !dragged) return;
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
      board.querySelectorAll('.is-drop-target').forEach(function (l) {
        if (l !== list) l.classList.remove('is-drop-target');
      });
      list.classList.add('is-drop-target');
    });

    board.addEventListener('drop', function (e) {
      var list = e.target.closest && e.target.closest('.board__list');
      if (!list || !dragged) return;
      e.preventDefault();
      list.classList.remove('is-drop-target');
      var status = list.dataset.status;
      if (dragged.dataset.status !== status) {
        changeStatus(dragged.querySelector('.status-form'), status);
      }
    });
  }
})();
