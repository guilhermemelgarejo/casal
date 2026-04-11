/**
 * Tour curto pós-criação de casal (conta → categoria → lançamento).
 * Config: window.__DUOZEN_ONBOARDING__ (definido em layouts.app).
 */
(function () {
    const cfg = window.__DUOZEN_ONBOARDING__;
    if (!cfg || !Array.isArray(cfg.steps)) {
        return;
    }

    const step = cfg.steps.find(function (s) {
        return s.route === cfg.route;
    });
    if (!step) {
        return;
    }

    let backdrop;
    let ring;
    let panel;
    let resizeObs;

    function destroyDom() {
        if (resizeObs) {
            try {
                resizeObs.disconnect();
            } catch (e) {
                /* ignore */
            }
            resizeObs = null;
        }
        window.removeEventListener('resize', layout);
        window.removeEventListener('scroll', layout, true);
        [backdrop, ring, panel].forEach(function (el) {
            if (el && el.parentNode) {
                el.parentNode.removeChild(el);
            }
        });
        backdrop = ring = panel = null;
        document.body.classList.remove('duozen-onboarding-active');
    }

    function dismiss() {
        const body = new URLSearchParams();
        body.set('_token', cfg.csrf);

        fetch(cfg.dismissUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                Accept: 'application/json',
                'X-CSRF-TOKEN': cfg.csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: body.toString(),
        })
            .catch(function () {
                /* ainda removemos a UI */
            })
            .finally(function () {
                destroyDom();
            });
    }

    function layout() {
        const el = document.querySelector(step.selector);
        if (!el || !ring || !panel) {
            return;
        }

        const r = el.getBoundingClientRect();
        const pad = 8;

        ring.style.top = r.top - pad + 'px';
        ring.style.left = r.left - pad + 'px';
        ring.style.width = r.width + pad * 2 + 'px';
        ring.style.height = r.height + pad * 2 + 'px';

        panel.style.maxWidth = 'min(22rem, calc(100vw - 2rem))';

        const panelHeight = panel.offsetHeight || 200;
        const gap = 12;
        let top = r.bottom + gap;
        if (top + panelHeight > window.innerHeight - 16) {
            top = r.top - panelHeight - gap;
        }
        if (top < 16) {
            top = 16;
        }

        let left = r.left + r.width / 2 - panel.offsetWidth / 2;
        const maxLeft = window.innerWidth - panel.offsetWidth - 16;
        if (left > maxLeft) {
            left = maxLeft;
        }
        if (left < 16) {
            left = 16;
        }
        panel.style.top = top + 'px';
        panel.style.left = left + 'px';
    }

    function build() {
        document.body.classList.add('duozen-onboarding-active');

        backdrop = document.createElement('div');
        backdrop.className = 'duozen-onboarding-backdrop';
        backdrop.setAttribute('aria-hidden', 'true');

        ring = document.createElement('div');
        ring.className = 'duozen-onboarding-ring';
        ring.setAttribute('aria-hidden', 'true');

        panel = document.createElement('div');
        panel.className = 'duozen-onboarding-panel card border-0 shadow-lg';
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-modal', 'true');
        panel.setAttribute('aria-labelledby', 'duozen-onboarding-title');

        const isLast = !step.nextUrl;

        panel.innerHTML =
            '<div class="card-body p-4">' +
            '<h2 id="duozen-onboarding-title" class="h6 fw-semibold mb-2">' +
            escapeHtml(step.title) +
            '</h2>' +
            '<p class="small text-secondary mb-4 mb-md-3">' +
            escapeHtml(step.body) +
            '</p>' +
            '<div class="d-flex flex-wrap align-items-center gap-2 justify-content-between">' +
            '<button type="button" class="btn btn-link btn-sm text-secondary text-decoration-none p-0 duozen-onboarding-skip">Saltar tour</button>' +
            '<div class="d-flex flex-wrap gap-2 ms-auto">' +
            (step.prevUrl
                ? '<button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3 duozen-onboarding-prev">Anterior</button>'
                : '') +
            (isLast
                ? '<button type="button" class="btn btn-primary btn-sm rounded-pill px-3 duozen-onboarding-done">Concluir</button>'
                : '<button type="button" class="btn btn-primary btn-sm rounded-pill px-3 duozen-onboarding-next">Seguinte</button>') +
            '</div></div></div>';

        document.body.appendChild(backdrop);
        document.body.appendChild(ring);
        document.body.appendChild(panel);

        panel.querySelector('.duozen-onboarding-skip').addEventListener('click', dismiss);
        const prevBtn = panel.querySelector('.duozen-onboarding-prev');
        if (prevBtn && step.prevUrl) {
            prevBtn.addEventListener('click', function () {
                window.location.href = step.prevUrl;
            });
        }
        const nextBtn = panel.querySelector('.duozen-onboarding-next');
        if (nextBtn && step.nextUrl) {
            nextBtn.addEventListener('click', function () {
                window.location.href = step.nextUrl;
            });
        }
        const doneBtn = panel.querySelector('.duozen-onboarding-done');
        if (doneBtn) {
            doneBtn.addEventListener('click', dismiss);
        }

        const target = document.querySelector(step.selector);
        if (target) {
            try {
                target.scrollIntoView({ block: 'center', behavior: 'auto' });
            } catch (e) {
                target.scrollIntoView(true);
            }
        }

        layout();
        window.addEventListener('resize', layout);
        window.addEventListener('scroll', layout, true);

        const el = document.querySelector(step.selector);
        if (el && typeof ResizeObserver !== 'undefined') {
            resizeObs = new ResizeObserver(function () {
                layout();
            });
            resizeObs.observe(el);
        }

        const focusable = panel.querySelector(
            isLast ? '.duozen-onboarding-done' : '.duozen-onboarding-next'
        ) || panel.querySelector('button');
        if (focusable && typeof focusable.focus === 'function') {
            focusable.focus();
        }
    }

    function escapeHtml(s) {
        if (!s) {
            return '';
        }
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', build);
    } else {
        build();
    }
})();
