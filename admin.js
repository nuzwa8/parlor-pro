(() => {
  'use strict';
  /**
   * Parlor Pro — Admin App
   * (IIFE) — utilities, screen inits, (AJAX), loaders, tables/forms, pagination
   */

  // -----------------------------
  // Guard: ssmData must exist
  // -----------------------------
  const hasData = typeof window.ssmData === 'object' && !!window.ssmData.ajaxUrl;
  if (!hasData) {
    console.warn('[Parlor Pro] ssmData غائب ہے — (wp_localize_script) چیک کریں۔');
    return;
  }

  // -----------------------------
  // Utilities
  // -----------------------------
  const $doc = jQuery(document);

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function money(val) {
    const n = Number(val || 0);
    return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function getQueryParam(key) {
    const url = new URL(window.location.href);
    return url.searchParams.get(key);
  }

  function ariaLiveRegion() {
    let $region = jQuery('#ssm-aria-live');
    if (!$region.length) {
      $region = jQuery('<div id="ssm-aria-live" class="screen-reader-text" aria-live="polite" aria-atomic="true"></div>');
      jQuery('body').append($region);
    }
    return $region;
  }

  function announce(msg) {
    ariaLiveRegion().text(msg);
  }

  function loader(on = true) {
    let $el = jQuery('#ssm-loader');
    if (on) {
      if (!$el.length) {
        $el = jQuery('<div id="ssm-loader" class="ssm ssm-loader" role="status" aria-live="polite"><span class="ssm-spinner" aria-hidden="true"></span><span class="ssm-loader-text"></span></div>');
        jQuery('body').append($el);
      }
      $el.find('.ssm-loader-text').text(ssmData?.i18n?.loading || 'لوڈ ہو رہا ہے...');
      $el.addClass('on');
    } else {
      $el.removeClass('on');
    }
  }

  function wpAjax(action, payload = {}, method = 'POST') {
    const data = {
      action,
      nonce: ssmData.nonce,
      ...payload
    };
    return jQuery.ajax({
      url: ssmData.ajaxUrl,
      method,
      data,
      dataType: 'json'
    }).then((res) => {
      if (!res || res.success !== true) {
        const msg = res && res.data && res.data.message ? res.data.message : (ssmData?.i18n?.error || 'کوئی مسئلہ آ گیا ہے');
        return jQuery.Deferred().reject({ message: msg }).promise();
      }
      return res.data;
    });
  }

  /**
   * mountTemplate:
   * موجودہ .wrap کے اندر ایک root کنٹینر بناتا ہے تاکہ JS UI محفوظ طریقے سے انجیکٹ ہو۔
   * اگر template (PHP) نے پہلے ہی HTML ڈالا ہو تو ہم اسے enhance کرتے ہیں۔
   */
  function mountTemplate(rootId, titleText) {
    const $wrap = jQuery('.wrap');
    if (!$wrap.length) {
      console.warn('[Parlor Pro] .wrap نہیں ملی (WordPress admin screen)۔');
      return null;
    }
    let $root = jQuery(`#${rootId}`);
    if (!$root.length) {
      $root = jQuery(`<div id="${rootId}" class="ssm ssm-root" tabindex="-1" aria-label="${escapeHtml(titleText || 'Parlor Pro')}"></div>`);
      $wrap.append($root);
    }
    try { $root[0].focus(); } catch (e) {}
    return $root;
  }

  // -----------------------------
  // Shared Widgets
  // -----------------------------
  function renderKpiCard(label, value, accent = 'primary') {
    return `
      <div class="ssm card kpi kpi-${accent}" role="group" aria-label="${escapeHtml(label)}">
        <div class="kpi-label">${escapeHtml(label)}</div>
        <div class="kpi-value">${escapeHtml(value)}</div>
      </div>
    `;
  }

  function renderPager(state) {
    const { page, per_page, total } = state;
    const pages = Math.max(1, Math.ceil(total / per_page));
    const prevDisabled = page <= 1 ? 'disabled' : '';
    const nextDisabled = page >= pages ? 'disabled' : '';
    return `
      <div class="ssm pager" role="navigation" aria-label="صفحہ بندی">
        <button type="button" class="button ssm btn prev" data-page="${page - 1}" ${prevDisabled} aria-label="پچھلا">
          ‹
        </button>
        <span class="ssm pager-info">${page} / ${pages}</span>
        <button type="button" class="button ssm btn next" data-page="${page + 1}" ${nextDisabled} aria-label="اگلا">
          ›
        </button>
      </div>
    `;
  }

  // -----------------------------
  // Screen: Dashboard
  // -----------------------------
  function initDashboard() {
    const $root = mountTemplate('ssm-dashboard-root', 'ڈیش بورڈ');
    if (!$root) return;

    loader(true);
    wpAjax('pp_get_dashboard')
      .then(({ data }) => data || {})
      .then((d) => {
        const html = `
          <div class="ssm header gradient-1">
            <h2>Parlor Pro — Dashboard</h2>
            <div class="sub">${escapeHtml(ssmData.siteUrl || '')}</div>
          </div>
          <div class="ssm grid kpis">
            ${renderKpiCard('کل کسٹمرز', String(d.total_clients || 0), 'primary')}
            ${renderKpiCard('آج کی اپائنٹمنٹس', String(d.today_appts || 0), 'cyan')}
            ${renderKpiCard('آج کی سیلز', money(d.today_sales || 0), 'coral')}
          </div>
          <div class="ssm panel">
            <h3>حالیہ سرگرمی</h3>
            <p class="muted">ڈیمو پینل — مزید تفصیل رپورٹس میں دستیاب ہوگی۔</p>
          </div>
        `;
        $root.html(html);
        announce('ڈیش بورڈ لوڈ ہو گیا');
      })
      .fail((err) => {
        $root.html(`<div class="ssm alert error" role="alert">${escapeHtml(err?.message || ssmData?.i18n?.error)}</div>`);
      })
      .always(() => loader(false));
  }

  // -----------------------------
  // Screen: Calendar (services list + create appointment)
  // -----------------------------
  function initCalendar() {
    const $root = mountTemplate('ssm-calendar-root', 'کیلنڈر');
    if (!$root) return;

    const state = { page: 1, per_page: 5, total: 0, rows: [] };

    function draw() {
      const rows = state.rows.map((r) => `
        <tr>
          <td>${escapeHtml(String(r.id))}</td>
          <td>${escapeHtml(r.title)}</td>
          <td>${money(r.price)}</td>
          <td>${escapeHtml(String(r.duration))} منٹ</td>
        </tr>
      `).join('');

      const table = `
        <table class="ssm table zebra" aria-describedby="services-caption">
          <caption id="services-caption" class="screen-reader-text">فعال سروسز</caption>
          <thead>
            <tr>
              <th scope="col">#</th>
              <th scope="col">سروس</th>
              <th scope="col">قیمت</th>
              <th scope="col">دورانیہ</th>
            </tr>
          </thead>
          <tbody>${rows || '<tr><td colspan="4" class="muted">کوئی فعال سروس نہیں ملی</td></tr>'}</tbody>
        </table>
      `;

      const pager = renderPager({ page: state.page, per_page: state.per_page, total: state.total });

      const form = `
        <form class="ssm form" id="ssm-appt-form" novalidate>
          <fieldset>
            <legend>نئی اپائنٹمنٹ</legend>
            <label>کلائنٹ آئی ڈی
              <input type="number" name="client_id" min="1" required />
            </label>
            <label>سروس آئی ڈی
              <input type="number" name="service_id" min="1" required />
            </label>
            <label>اسٹاف آئی ڈی
              <input type="number" name="staff_id" min="1" required />
            </label>
            <label>آغاز (YYYY-MM-DD HH:MM:SS)
              <input type="text" name="start_time" placeholder="2025-01-01 10:00:00" required />
            </label>
            <label>اختتام (YYYY-MM-DD HH:MM:SS)
              <input type="text" name="end_time" placeholder="2025-01-01 11:00:00" required />
            </label>
            <label>اسٹیٹس
              <select name="status">
                <option value="pending">pending</option>
                <option value="confirmed">confirmed</option>
                <option value="completed">completed</option>
                <option value="cancelled">cancelled</option>
              </select>
            </label>
            <label>نوٹس
              <textarea name="notes" rows="2"></textarea>
            </label>
            <div class="actions">
              <button type="submit" class="button button-primary">محفوظ کریں</button>
              <span class="ssm form-note" aria-live="polite"></span>
            </div>
          </fieldset>
        </form>
      `;

      $root.html(`
        <div class="ssm header gradient-1">
          <h2>Calendar</h2>
          <div class="sub">فعال سروسز اور فوری اپائنٹمنٹ فارم</div>
        </div>
        <div class="ssm grid two">
          <div class="ssm panel">
            <h3>سروسز</h3>
            <div class="table-wrap">${table}</div>
            ${pager}
          </div>
          <div class="ssm panel">
            ${form}
          </div>
        </div>
      `);
    }

    function loadServices() {
      loader(true);
      return wpAjax('pp_get_services', { page_no: state.page, per_page: state.per_page }, 'GET')
        .then(({ data }) => data || {})
        .then((d) => {
          state.total = Number(d.total || 0);
          state.rows = Array.isArray(d.rows) ? d.rows : [];
          draw();
          announce('سروسز لوڈ ہو گئیں');
        })
        .fail((err) => {
          $root.html(`<div class="ssm alert error" role="alert">${escapeHtml(err?.message || ssmData?.i18n?.error)}</div>`);
        })
        .always(() => loader(false));
    }

    // Delegated events
    $root.on('click', '.pager .btn', function () {
      const to = Number(jQuery(this).data('page'));
      if (isNaN(to) || to < 1) return;
      const pages = Math.max(1, Math.ceil(state.total / state.per_page));
      if (to > pages) return;
      state.page = to;
      loadServices();
    });

    $root.on('submit', '#ssm-appt-form', function (e) {
      e.preventDefault();
      const $f = jQuery(this);
      const formData = {
        id: 0,
        client_id: $f.find('[name="client_id"]').val(),
        service_id: $f.find('[name="service_id"]').val(),
        staff_id: $f.find('[name="staff_id"]').val(),
        start_time: $f.find('[name="start_time"]').val(),
        end_time: $f.find('[name="end_time"]').val(),
        status: $f.find('[name="status"]').val(),
        notes: $f.find('[name="notes"]').val()
      };
      loader(true);
      wpAjax('pp_save_appointment', formData, 'POST')
        .then(({ data }) => data || {})
        .then((d) => {
          $f[0].reset();
          $f.find('.form-note').text(ssmData?.i18n?.saved || 'محفوظ ہو گیا');
          announce('اپائنٹمنٹ محفوظ ہو گئی');
        })
        .fail((err) => {
          $f.find('.form-note').text(err?.message || ssmData?.i18n?.error);
        })
        .always(() => loader(false));
    });

    // Initial load
    loadServices();
  }

  // -----------------------------
  // Screen: POS (UI-only stub — no AJAX call yet)
  // -----------------------------
  function initPOS() {
    const $root = mountTemplate('ssm-pos-root', 'پی او ایس');
    if (!$root) return;

    const html = `
      <div class="ssm header gradient-2">
        <h2>Point of Sale</h2>
        <div class="sub">فوری بلنگ انٹرفیس (ڈیمو)</div>
      </div>
      <div class="ssm grid two">
        <div class="ssm panel">
          <h3>آئٹمز</h3>
          <div class="ssm keypad">
            <button type="button" class="button ssm pill" data-price="500">ہیئر کٹ — 500</button>
            <button type="button" class="button ssm pill" data-price="1200">فیشل — 1200</button>
            <button type="button" class="button ssm pill" data-price="800">بلیچ — 800</button>
          </div>
        </div>
        <div class="ssm panel">
          <h3>ٹوٹل</h3>
          <div class="ssm total-bar">
            <span class="label">کل</span>
            <span class="value" id="ssm-pos-total">0.00</span>
          </div>
          <div class="actions">
            <button type="button" class="button button-primary" id="ssm-pos-pay">ادائیگی</button>
            <span class="muted">(ڈیمو — ابھی کوئی (AJAX) کال نہیں)</span>
          </div>
        </div>
      </div>
    `;
    $root.html(html);

    let total = 0;
    $root.on('click', '.keypad .pill', function () {
      const price = Number(jQuery(this).data('price') || 0);
      total += price;
      $root.find('#ssm-pos-total').text(money(total));
      announce(`کل ${money(total)}`);
    });

    $root.on('click', '#ssm-pos-pay', function () {
      alert('یہ ڈیمو ہے — (save_order) (AJAX) مستقبل میں شامل کریں گے۔');
    });
  }

  // -----------------------------
  // Screen: Reports
  // -----------------------------
  function initReports() {
    const $root = mountTemplate('ssm-reports-root', 'رپورٹس');
    if (!$root) return;

    function defaultRange() {
      const now = new Date();
      const y = now.getUTCFullYear();
      const m = String(now.getUTCMonth() + 1).padStart(2, '0');
      const first = `${y}-${m}-01`;
      const lastDate = new Date(Date.UTC(y, now.getUTCMonth() + 1, 0)).getUTCDate();
      const last = `${y}-${m}-${String(lastDate).padStart(2, '0')}`;
      return { from: first, to: last };
    }

    const range = defaultRange();

    function draw(d) {
      const html = `
        <div class="ssm header gradient-1">
          <h2>Reports</h2>
          <div class="sub">مدت منتخب کریں اور نتائج دیکھیں</div>
        </div>
        <div class="ssm panel">
          <form id="ssm-report-form" class="ssm form">
            <label>ابتداء
              <input type="date" name="from" value="${escapeHtml(range.from)}" required />
            </label>
            <label>اختتام
              <input type="date" name="to" value="${escapeHtml(range.to)}" required />
            </label>
            <button type="submit" class="button button-primary">فِلٹر</button>
          </form>
        </div>
        <div class="ssm grid kpis">
          ${renderKpiCard('مدت', `${escapeHtml(d?.range?.from || '')} → ${escapeHtml(d?.range?.to || '')}`, 'primary')}
          ${renderKpiCard('کل سیلز', money(d?.total_sales || 0), 'coral')}
          ${renderKpiCard('کل اپائنٹمنٹس', String(d?.appointments || 0), 'cyan')}
        </div>
      `;
      $root.html(html);
    }

    function load() {
      loader(true);
      wpAjax('pp_get_reports', { from: range.from, to: range.to }, 'GET')
        .then(({ data }) => data || {})
        .then((d) => {
          draw(d);
          announce('رپورٹس لوڈ ہو گئیں');
        })
        .fail((err) => {
          $root.html(`<div class="ssm alert error" role="alert">${escapeHtml(err?.message || ssmData?.i18n?.error)}</div>`);
        })
        .always(() => loader(false));
    }

    $root.on('submit', '#ssm-report-form', function (e) {
      e.preventDefault();
      const $f = jQuery(this);
      range.from = $f.find('[name="from"]').val();
      range.to = $f.find('[name="to"]').val();
      load();
    });

    load();
  }

  // -----------------------------
  // Screen: Settings (enhance)
  // -----------------------------
  function initSettings() {
    const $wrap = jQuery('.wrap');
    if (!$wrap.length) return;
    $wrap.find('form[action="options.php"]').addClass('ssm form');
    $wrap.prepend(`<div class="ssm header gradient-2"><h2>Settings</h2><div class="sub">جنرل آپشنز</div></div>`);
  }

  // -----------------------------
  // Router (by page param)
  // -----------------------------
  function route() {
    const page = getQueryParam('page') || '';
    if (page === 'parlor-pro' || page === '') {
      initDashboard();
    } else if (page === 'parlor-pro-calendar') {
      initCalendar();
    } else if (page === 'parlor-pro-pos') {
      initPOS();
    } else if (page === 'parlor-pro-reports') {
      initReports();
    } else if (page === 'parlor-pro-settings') {
      initSettings();
    } else {
      // Unknown page — no-op
    }
  }

  // -----------------------------
  // Boot
  // -----------------------------
  jQuery(() => {
    try {
      route();
    } catch (e) {
      console.error('[Parlor Pro] init error:', e);
    }
  });

})();


// =============================
// Services Catalog Panel (Additive)
// =============================
(function(){
  const $ = window.jQuery || function(s){ return document.querySelector(s); };

  function renderServicesPanel(root) {
    if (!root) return;
    const panel = document.createElement('div');
    panel.className = 'ssm card ssm-services-panel';
    panel.innerHTML = '<div class="title">سروسز کی کیٹیگریز</div><div class="ssm-services-body"><div class="loader">لوڈ ہو رہا ہے…</div></div>';
    root.appendChild(panel);

    // Fetch catalog
    const url = (window.ssmData && window.ssmData.ajaxUrl) ? window.ssmData.ajaxUrl : '';
    if (!url) return;
    const params = new URLSearchParams({ action: 'pp_get_services_catalog', _wpnonce: (window.ssmData && window.ssmData.nonce) || '' });

    fetch(url + '?' + params.toString(), { credentials: 'same-origin' })
      .then(r => r.json())
      .then(payload => {
        const body = panel.querySelector('.ssm-services-body');
        if (!payload || !payload.success) {
          body.textContent = 'ڈیٹا حاصل نہیں ہو سکا۔';
          return;
        }
        const cat = payload.data.catalog || {};
        const wrapper = document.createElement('div');
        wrapper.className = 'ssm-services-grid';

        Object.keys(cat).forEach(key => {
          const c = cat[key];
          const card = document.createElement('div');
          card.className = 'ssm service-cat';
          const h = document.createElement('h4');
          h.textContent = c.title || key;
          card.appendChild(h);

          const ul = document.createElement('ul');
          (c.items || []).forEach(it => {
            const li = document.createElement('li');
            li.innerHTML = '<span class="n"></span><code class="cd"></code>';
            li.querySelector('.n').textContent = it.name || '';
            li.querySelector('.cd').textContent = it.code || '';
            ul.appendChild(li);
          });
          card.appendChild(ul);
          wrapper.appendChild(card);
        });

        body.innerHTML = '';
        body.appendChild(wrapper);
      })
      .catch(() => {
        const body = panel.querySelector('.ssm-services-body');
        body.textContent = 'کچھ خرابی پیش آگئی۔';
      });
  }

  // Try to mount on dashboard if present
  jQuery(function(){
    const root = document.getElementById('ssm-dashboard-root');
    if (root) renderServicesPanel(root);
  });
})();
