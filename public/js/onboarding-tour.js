/**
 * Tour guiado de boas-vindas do DuoZen 2.0.
 * Configuração injetada via window.__DUOZEN_ONBOARDING__ em layouts.app.
 */
(function () {
    const cfg = window.__DUOZEN_ONBOARDING__;
    if (!cfg || !Array.isArray(cfg.steps) || !cfg.steps.length) {
        return;
    }

    function resolveOnboardingStep() {
        const routeName = cfg.route;
        const params = new URLSearchParams(window.location.search);
        const onRoute = cfg.steps.filter(function (s) {
            return s.route === routeName;
        });

        // 1. Prioriza passos com whenQuery matching
        for (let i = 0; i < onRoute.length; i++) {
            const s = onRoute[i];
            const wq = s.whenQuery;
            if (wq && typeof wq === 'object' && Object.keys(wq).length) {
                const ok = Object.keys(wq).every(function (key) {
                    return params.get(key) === String(wq[key]);
                });
                if (ok) {
                    return s;
                }
            }
        }

        // 2. Passos sem whenQuery
        for (let i = 0; i < onRoute.length; i++) {
            const s = onRoute[i];
            const wq = s.whenQuery;
            if (!wq || typeof wq !== 'object' || !Object.keys(wq).length) {
                return s;
            }
        }

        return null;
    }

    const step = resolveOnboardingStep();
    if (!step) {
        return;
    }

    let backdrop;
    let ring;
    let panel;
    let resizeObs;
    let keyHandler;

    function destroyDom() {
        if (resizeObs) {
            try {
                resizeObs.disconnect();
            } catch (e) {
                /* ignore */
            }
            resizeObs = null;
        }
        if (keyHandler) {
            window.removeEventListener('keydown', keyHandler);
            keyHandler = null;
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
        if (!panel) {
            return;
        }

        const el = step.selector ? document.querySelector(step.selector) : null;
        const pad = 8;
        const gap = 14;

        if (el && ring) {
            ring.style.display = 'block';
            const r = el.getBoundingClientRect();

            ring.style.top = (r.top - pad) + 'px';
            ring.style.left = (r.left - pad) + 'px';
            ring.style.width = (r.width + pad * 2) + 'px';
            ring.style.height = (r.height + pad * 2) + 'px';

            const panelWidth = Math.min(360, window.innerWidth - 32);
            panel.style.width = panelWidth + 'px';
            const panelHeight = panel.offsetHeight || 220;

            let top = r.bottom + gap;
            // Se não couber embaixo, tenta colocar em cima
            if (top + panelHeight > window.innerHeight - 16) {
                top = r.top - panelHeight - gap;
            }
            // Garante que não saia da tela
            if (top < 16) {
                top = 16;
            }
            if (top + panelHeight > window.innerHeight - 16) {
                top = Math.max(16, window.innerHeight - panelHeight - 16);
            }

            let left = r.left + (r.width / 2) - (panelWidth / 2);
            const maxLeft = window.innerWidth - panelWidth - 16;
            if (left > maxLeft) {
                left = maxLeft;
            }
            if (left < 16) {
                left = 16;
            }

            panel.style.top = top + 'px';
            panel.style.left = left + 'px';
        } else {
            // Se o elemento não existir, centraliza o card
            if (ring) {
                ring.style.display = 'none';
            }
            const panelWidth = Math.min(380, window.innerWidth - 32);
            panel.style.width = panelWidth + 'px';
            const panelHeight = panel.offsetHeight || 220;

            panel.style.top = Math.max(16, (window.innerHeight - panelHeight) / 2) + 'px';
            panel.style.left = Math.max(16, (window.innerWidth - panelWidth) / 2) + 'px';
        }
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
        panel.className = 'duozen-onboarding-panel card shadow-lg';
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-modal', 'true');
        panel.setAttribute('aria-labelledby', 'duozen-onboarding-title');

        const isLast = !step.nextUrl;
        const stepNum = step.step;
        const totalSteps = step.total;

        let badgeHtml = '';
        if (stepNum && totalSteps) {
            badgeHtml = '<span class="badge rounded-pill bg-primary-subtle text-primary fw-bold px-2 py-1" style="font-size: 0.72rem; letter-spacing: 0.03em;">Passo ' +
                escapeHtml(stepNum) + ' de ' + escapeHtml(totalSteps) + '</span>';
        }

        panel.innerHTML =
            '<div class="card-body p-4">' +
            '<div class="d-flex align-items-center justify-content-between mb-2">' +
            badgeHtml +
            '<button type="button" class="btn-close ms-auto duozen-onboarding-close" aria-label="Fechar tour" title="Fechar tour"></button>' +
            '</div>' +
            '<h2 id="duozen-onboarding-title" class="h6 fw-bold mb-2" style="color: var(--dz-text-title, inherit);">' +
            escapeHtml(step.title) +
            '</h2>' +
            '<p class="small mb-4" style="color: var(--dz-text-secondary, #64748B); line-height: 1.55;">' +
            escapeHtml(step.body) +
            '</p>' +
            '<div class="d-flex flex-wrap align-items-center gap-2 justify-content-between">' +
            '<button type="button" class="btn btn-link btn-sm text-secondary text-decoration-none p-0 duozen-onboarding-skip" style="font-size: 0.8rem;">Pular tour</button>' +
            '<div class="d-flex flex-wrap gap-2 ms-auto">' +
            (step.prevUrl
                ? '<button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3 duozen-onboarding-prev" style="font-size: 0.8rem; font-weight: 600;">Anterior</button>'
                : '') +
            (isLast
                ? '<button type="button" class="btn btn-primary btn-sm rounded-pill px-3 duozen-onboarding-done" style="font-size: 0.8rem; font-weight: 600; background: var(--dz-primary, #6366F1); border-color: var(--dz-primary, #6366F1);">Concluir 🎉</button>'
                : '<button type="button" class="btn btn-primary btn-sm rounded-pill px-3 duozen-onboarding-next" style="font-size: 0.8rem; font-weight: 600; background: var(--dz-primary, #6366F1); border-color: var(--dz-primary, #6366F1);">Próximo →</button>') +
            '</div></div></div>';

        document.body.appendChild(backdrop);
        document.body.appendChild(ring);
        document.body.appendChild(panel);

        // Eventos
        backdrop.addEventListener('click', dismiss);
        panel.querySelector('.duozen-onboarding-close')?.addEventListener('click', dismiss);
        panel.querySelector('.duozen-onboarding-skip')?.addEventListener('click', dismiss);

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

        keyHandler = function (e) {
            if (e.key === 'Escape') {
                dismiss();
            }
        };
        window.addEventListener('keydown', keyHandler);

        // Scroll e Layout inicial
        const target = step.selector ? document.querySelector(step.selector) : null;
        if (target) {
            try {
                target.scrollIntoView({ block: 'center', behavior: 'smooth' });
            } catch (e) {
                target.scrollIntoView(true);
            }
        }

        layout();
        requestAnimationFrame(layout);
        setTimeout(layout, 120);

        window.addEventListener('resize', layout);
        window.addEventListener('scroll', layout, true);

        if (target && typeof ResizeObserver !== 'undefined') {
            resizeObs = new ResizeObserver(function () {
                layout();
            });
            resizeObs.observe(target);
        }

        const focusable = panel.querySelector(
            isLast ? '.duozen-onboarding-done' : '.duozen-onboarding-next'
        ) || panel.querySelector('button');
        if (focusable && typeof focusable.focus === 'function') {
            focusable.focus();
        }
    }

    function escapeHtml(s) {
        if (s === null || s === undefined) {
            return '';
        }
        const d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', build);
    } else {
        build();
    }
})();
