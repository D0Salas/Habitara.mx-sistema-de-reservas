/**
 * Widget de reservas Habitara.
 *
 * Uso con UNA sola propiedad (como antes, retrocompatible):
 *   HabitaraBooking.init({
 *     container: '#hb-booking',
 *     apiBase: '/api',
 *     propertyId: 'casa-quintana-roo'
 *   });
 *
 * Uso con VARIAS propiedades (agrega una fila de selección arriba
 * de los cuartos; útil para Tabasco, que tiene 3 casas, y para
 * cuando Quintana Roo agregue más a futuro):
 *   HabitaraBooking.init({
 *     container: '#hb-booking',
 *     apiBase: '/api',
 *     properties: [
 *       { id: 'tab-puerta-hierro', name: 'Puerta de Hierro' },
 *       { id: 'tab-jacana',        name: 'Jacaná' },
 *       { id: 'tab-carrancho',     name: 'Carrancho' }
 *     ]
 *   });
 */
(function (global) {
  const MESES = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
  const DIAS = ['D','L','M','M','J','V','S'];

  function fmt(date) {
    return date.toISOString().slice(0, 10);
  }

  function init(opts) {
    const el = typeof opts.container === 'string' ? document.querySelector(opts.container) : opts.container;
    if (!el) { console.error('HabitaraBooking: contenedor no encontrado'); return; }

    // Retrocompatible: si solo pasan propertyId, se arma un arreglo de una sola propiedad
    // (sin nombre); la fila de propiedades no se muestra cuando hay solo una.
    const properties = opts.properties && opts.properties.length
      ? opts.properties
      : [{ id: opts.propertyId, name: null }];

    const state = {
      apiBase: opts.apiBase.replace(/\/$/, ''),
      properties,
      activePropertyId: properties[0].id,
      rooms: [],
      activeRoomId: null,
      viewMonth: new Date(new Date().getFullYear(), new Date().getMonth(), 1),
      selStart: null,
      selEnd: null,
    };

    el.classList.add('hb-widget');
    el.innerHTML = '<div class="hb-properties"></div><div class="hb-rooms"></div><div class="hb-body"></div>';
    const propsEl = el.querySelector('.hb-properties');
    const roomsEl = el.querySelector('.hb-rooms');
    const bodyEl = el.querySelector('.hb-body');

    renderPropertyTabs(state, propsEl, () => switchProperty(state, propsEl, roomsEl, bodyEl));

    fetchAvailability(state).then(() => {
      renderRoomTabs(state, roomsEl, () => renderAll(state, bodyEl, roomsEl));
      renderAll(state, bodyEl, roomsEl);
    });
  }

  function switchProperty(state, propsEl, roomsEl, bodyEl) {
    state.activeRoomId = null;
    state.selStart = null;
    state.selEnd = null;
    [...propsEl.children].forEach(b => b.classList.toggle('active', b.dataset.propertyId === state.activePropertyId));
    fetchAvailability(state).then(() => {
      renderRoomTabs(state, roomsEl, () => renderAll(state, bodyEl, roomsEl));
      renderAll(state, bodyEl, roomsEl);
    });
  }

  function renderPropertyTabs(state, propsEl, onChange) {
    propsEl.innerHTML = '';
    if (state.properties.length <= 1) {
      propsEl.style.display = 'none';
      return; // una sola propiedad: no hace falta el selector (Quintana Roo, por ahora)
    }
    propsEl.style.display = '';
    state.properties.forEach(prop => {
      const btn = document.createElement('button');
      btn.className = 'hb-property-tab' + (prop.id === state.activePropertyId ? ' active' : '');
      btn.textContent = prop.name || prop.id;
      btn.dataset.propertyId = prop.id;
      btn.onclick = () => {
        state.activePropertyId = prop.id;
        onChange();
      };
      propsEl.appendChild(btn);
    });
  }

  async function fetchAvailability(state) {
    const start = fmt(new Date());
    const end = fmt(new Date(new Date().setMonth(new Date().getMonth() + 8)));
    const res = await fetch(`${state.apiBase}/availability.php?property_id=${encodeURIComponent(state.activePropertyId)}&start=${start}&end=${end}`);
    const data = await res.json();
    state.rooms = data.rooms || [];
    state.activeRoomId = state.rooms.length ? state.rooms[0].room_id : null;
  }

  function renderRoomTabs(state, roomsEl, onChange) {
    roomsEl.innerHTML = '';
    state.rooms.forEach(room => {
      const btn = document.createElement('button');
      btn.className = 'hb-room-tab' + (room.room_id === state.activeRoomId ? ' active' : '');
      btn.textContent = room.room_name;
      btn.onclick = () => {
        state.activeRoomId = room.room_id;
        state.selStart = null;
        state.selEnd = null;
        onChange();
      };
      roomsEl.appendChild(btn);
    });
  }

  function currentRoom(state) {
    return state.rooms.find(r => r.room_id === state.activeRoomId);
  }

  function renderAll(state, bodyEl, roomsEl) {
    [...roomsEl.children].forEach(b => b.classList.toggle('active', b.textContent === (currentRoom(state) || {}).room_name));
    renderCalendar(state, bodyEl);
  }

  function renderCalendar(state, bodyEl) {
    const room = currentRoom(state);
    bodyEl.innerHTML = '';
    if (!room) { bodyEl.innerHTML = '<p style="padding:24px;">No hay cuartos disponibles para esta propiedad.</p>'; return; }

    const occupied = new Set(room.occupied_nights);
    const today = new Date(); today.setHours(0,0,0,0);

    // --- navegación de mes ---
    const nav = document.createElement('div');
    nav.className = 'hb-month-nav';
    const prevBtn = document.createElement('button');
    prevBtn.textContent = '‹';
    prevBtn.disabled = state.viewMonth <= new Date(today.getFullYear(), today.getMonth(), 1);
    prevBtn.onclick = () => { state.viewMonth = new Date(state.viewMonth.getFullYear(), state.viewMonth.getMonth() - 1, 1); renderCalendar(state, bodyEl); };
    const nextBtn = document.createElement('button');
    nextBtn.textContent = '›';
    nextBtn.onclick = () => { state.viewMonth = new Date(state.viewMonth.getFullYear(), state.viewMonth.getMonth() + 1, 1); renderCalendar(state, bodyEl); };
    const label = document.createElement('div');
    label.className = 'hb-month-label';
    label.textContent = `${MESES[state.viewMonth.getMonth()]} ${state.viewMonth.getFullYear()}`;
    nav.append(prevBtn, label, nextBtn);
    bodyEl.appendChild(nav);

    // --- cuadrícula ---
    const grid = document.createElement('div');
    grid.className = 'hb-grid';
    DIAS.forEach(d => {
      const dow = document.createElement('div');
      dow.className = 'hb-dow';
      dow.textContent = d;
      grid.appendChild(dow);
    });

    const firstOfMonth = new Date(state.viewMonth.getFullYear(), state.viewMonth.getMonth(), 1);
    const startOffset = firstOfMonth.getDay();
    const daysInMonth = new Date(state.viewMonth.getFullYear(), state.viewMonth.getMonth() + 1, 0).getDate();

    for (let i = 0; i < startOffset; i++) {
      const empty = document.createElement('div');
      empty.className = 'hb-day hb-empty';
      grid.appendChild(empty);
    }

    for (let day = 1; day <= daysInMonth; day++) {
      const date = new Date(state.viewMonth.getFullYear(), state.viewMonth.getMonth(), day);
      const iso = fmt(date);
      const cell = document.createElement('div');
      cell.textContent = day;

      const isPast = date < today;
      const isBlocked = occupied.has(iso);

      if (isPast) {
        cell.className = 'hb-day hb-past';
      } else if (isBlocked) {
        cell.className = 'hb-day hb-blocked';
      } else {
        cell.className = 'hb-day hb-selectable';
        if (state.selStart && iso === fmt(state.selStart)) cell.classList.add('hb-range-edge');
        if (state.selEnd && iso === fmt(state.selEnd)) cell.classList.add('hb-range-edge');
        if (state.selStart && state.selEnd && date > state.selStart && date < state.selEnd) cell.classList.add('hb-in-range');

        cell.onclick = () => handleDayClick(state, date, occupied, () => renderCalendar(state, bodyEl));
      }
      grid.appendChild(cell);
    }
    bodyEl.appendChild(grid);

    renderSummary(state, bodyEl, room);
  }

  function rangeHasBlockedNight(start, end, occupied) {
    const cursor = new Date(start);
    while (cursor < end) {
      if (occupied.has(fmt(cursor))) return true;
      cursor.setDate(cursor.getDate() + 1);
    }
    return false;
  }

  function handleDayClick(state, date, occupied, rerender) {
    if (!state.selStart || (state.selStart && state.selEnd)) {
      state.selStart = date;
      state.selEnd = null;
    } else {
      if (date <= state.selStart) {
        state.selStart = date;
        state.selEnd = null;
      } else if (rangeHasBlockedNight(state.selStart, date, occupied)) {
        state.selStart = date;
        state.selEnd = null;
      } else {
        state.selEnd = date;
      }
    }
    rerender();
  }

  function renderSummary(state, bodyEl, room) {
    const wrap = document.createElement('div');
    wrap.className = 'hb-summary';

    const info = document.createElement('div');
    info.className = 'hb-summary-info';

    if (state.selStart && state.selEnd) {
      const nights = Math.round((state.selEnd - state.selStart) / 86400000);
      const total = nights * room.price_night;
      info.innerHTML = `<h4>${nights} noche${nights === 1 ? '' : 's'}</h4>
        ${fmt(state.selStart)} → ${fmt(state.selEnd)}<br>
        $${total.toLocaleString('es-MX')} MXN total`;
    } else if (state.selStart) {
      info.innerHTML = `<h4>Elige tu salida</h4>Entrada: ${fmt(state.selStart)}`;
    } else {
      info.innerHTML = `<h4>Elige tus fechas</h4>Toca un día de entrada y luego uno de salida.`;
      info.innerHTML += `<div class="hb-note">$${room.price_night.toLocaleString('es-MX')} MXN / noche</div>`;
    }
    wrap.appendChild(info);

    if (state.selStart && state.selEnd) {
      const form = document.createElement('form');
      form.className = 'hb-form';
      form.innerHTML = `
        <input name="guest_name" placeholder="Nombre completo" required>
        <input name="guest_email" type="email" placeholder="Correo electrónico">
        <input name="guest_phone" placeholder="Teléfono / WhatsApp" required>
        <button type="submit">Reservar directo con Habitara</button>
      `;
      form.onsubmit = (e) => submitBooking(e, state, room, bodyEl);
      wrap.appendChild(form);
    }

    bodyEl.appendChild(wrap);
  }

  async function submitBooking(e, state, room, bodyEl) {
    e.preventDefault();
    const form = e.target;
    const btn = form.querySelector('button');
    btn.disabled = true;
    btn.textContent = 'Enviando...';

    const payload = {
      room_id: room.room_id,
      check_in: fmt(state.selStart),
      check_out: fmt(state.selEnd),
      guest_name: form.guest_name.value,
      guest_email: form.guest_email.value,
      guest_phone: form.guest_phone.value,
    };

    try {
      const res = await fetch(`${state.apiBase}/create_booking.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();

      if (res.ok) {
        showMessage(bodyEl, 'success', `¡Reserva confirmada! Nos pondremos en contacto contigo al ${payload.guest_phone}.`);
        state.selStart = null;
        state.selEnd = null;
        await fetchAvailability(state);
        renderCalendar(state, bodyEl);
      } else {
        showMessage(bodyEl, 'error', data.error || 'No se pudo completar la reserva.');
        await fetchAvailability(state);
        renderCalendar(state, bodyEl);
      }
    } catch (err) {
      showMessage(bodyEl, 'error', 'Error de conexión. Intenta de nuevo o escríbenos por WhatsApp.');
    }
  }

  function showMessage(bodyEl, type, text) {
    const msg = document.createElement('div');
    msg.className = `hb-message hb-${type}`;
    msg.textContent = text;
    bodyEl.appendChild(msg);
  }

  global.HabitaraBooking = { init };
})(window);
