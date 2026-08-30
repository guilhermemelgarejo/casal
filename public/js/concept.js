/**
 * DuoZen 2.0 - Concept Layout Interactivity Script
 */
document.addEventListener('DOMContentLoaded', function () {
    // 1. Theme Management (Light / Dark)
    const themePills = document.querySelectorAll('[data-dz-theme]');
    const themeToggleBtns = document.querySelectorAll('#dz-theme-toggle');
    const rootHtml = document.documentElement;

    function applyTheme(theme) {
        rootHtml.setAttribute('data-theme', theme);
        themePills.forEach(pill => {
            pill.classList.toggle('active', pill.getAttribute('data-dz-theme') === theme);
        });

        // Atualiza ícones Sol / Lua no botão da topbar
        themeToggleBtns.forEach(btn => {
            const moonIcon = btn.querySelector('.dz-icon-moon');
            const sunIcon = btn.querySelector('.dz-icon-sun');
            if (moonIcon && sunIcon) {
                if (theme === 'dark') {
                    moonIcon.classList.add('d-none');
                    sunIcon.classList.remove('d-none');
                    btn.setAttribute('title', 'Alternar para modo claro');
                } else {
                    moonIcon.classList.remove('d-none');
                    sunIcon.classList.add('d-none');
                    btn.setAttribute('title', 'Alternar para modo escuro');
                }
            }
        });

        localStorage.setItem('duozen_concept_theme', theme);
        localStorage.setItem('duozen_theme', theme);
    }

    const savedTheme = localStorage.getItem('duozen_concept_theme') || localStorage.getItem('duozen_theme') || 'light';
    applyTheme(savedTheme);

    themePills.forEach(pill => {
        pill.addEventListener('click', function () {
            applyTheme(this.getAttribute('data-dz-theme'));
        });
    });

    themeToggleBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            const currentTheme = rootHtml.getAttribute('data-theme') || 'light';
            const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';
            applyTheme(nextTheme);
        });
    });

    // 2. Privacy Mode Toggle (Blur Currency)
    const privacyBtn = document.getElementById('dz-privacy-toggle');
    const privacyPills = document.querySelectorAll('[data-dz-privacy]');

    function togglePrivacy(active) {
        if (active) {
            document.body.classList.add('dz-privacy-active');
            rootHtml.classList.add('duozen-privacy-active');
        } else {
            document.body.classList.remove('dz-privacy-active');
            rootHtml.classList.remove('duozen-privacy-active');
        }
        privacyPills.forEach(p => p.classList.toggle('active', p.getAttribute('data-dz-privacy') === String(active)));
        localStorage.setItem('duozen_privacy_mode', String(active));
    }

    const initialPrivacy = localStorage.getItem('duozen_privacy_mode') === 'true';
    togglePrivacy(initialPrivacy);

    privacyPills.forEach(pill => {
        pill.addEventListener('click', function () {
            const val = this.getAttribute('data-dz-privacy') === 'true';
            togglePrivacy(val);
        });
    });

    if (privacyBtn) {
        privacyBtn.addEventListener('click', function () {
            const isCurrentlyActive = document.body.classList.contains('dz-privacy-active');
            togglePrivacy(!isCurrentlyActive);
        });
    }



    // 4. Prototype Demo Handlers (Ativos somente na página de demonstração /concept)
    const isConceptDemo = document.body.classList.contains('dz-concept-body');
    if (isConceptDemo) {
        const partnerPills = document.querySelectorAll('[data-dz-partner]');
        const txRows = document.querySelectorAll('.dz-tx-row');
        const kpiIncome = document.getElementById('dz-kpi-income');
        const kpiExpense = document.getElementById('dz-kpi-expense');
        const kpiResult = document.getElementById('dz-kpi-result');
        const kpiPressure = document.getElementById('dz-kpi-pressure');
        const kpiPressureBar = document.getElementById('dz-kpi-pressure-bar');

        partnerPills.forEach(pill => {
            pill.addEventListener('click', function () {
                partnerPills.forEach(p => p.classList.remove('active'));
                this.classList.add('active');
                const partner = this.getAttribute('data-dz-partner');

                // Filter transaction feed
                txRows.forEach(row => {
                    const payer = row.getAttribute('data-payer');
                    if (partner === 'all') {
                        row.style.display = 'flex';
                    } else if (partner === 'user1' && (payer === 'Guilherme' || payer === 'Casal')) {
                        row.style.display = 'flex';
                    } else if (partner === 'user2' && (payer === 'Mariana' || payer === 'Casal')) {
                        row.style.display = 'flex';
                    } else {
                        row.style.display = 'none';
                    }
                });

                // Update KPI dynamic metrics demonstration
                if (partner === 'user1') {
                    if (kpiIncome) kpiIncome.innerText = 'R$ 8.500,00';
                    if (kpiExpense) kpiExpense.innerText = 'R$ 5.120,00';
                    if (kpiResult) {
                        kpiResult.innerText = 'R$ 3.380,00';
                        kpiResult.className = 'dz-kpi-card__value text-success dz-privacy-blur';
                    }
                    if (kpiPressure) kpiPressure.innerText = '60,2% da renda';
                    if (kpiPressureBar) kpiPressureBar.style.width = '60.2%';
                } else if (partner === 'user2') {
                    if (kpiIncome) kpiIncome.innerText = 'R$ 6.350,00';
                    if (kpiExpense) kpiExpense.innerText = 'R$ 4.220,50';
                    if (kpiResult) {
                        kpiResult.innerText = 'R$ 2.129,50';
                        kpiResult.className = 'dz-kpi-card__value text-success dz-privacy-blur';
                    }
                    if (kpiPressure) kpiPressure.innerText = '66,4% da renda';
                    if (kpiPressureBar) kpiPressureBar.style.width = '66.4%';
                } else {
                    if (kpiIncome) kpiIncome.innerText = 'R$ 14.850,00';
                    if (kpiExpense) kpiExpense.innerText = 'R$ 9.340,50';
                    if (kpiResult) {
                        kpiResult.innerText = 'R$ 5.509,50';
                        kpiResult.className = 'dz-kpi-card__value text-success dz-privacy-blur';
                    }
                    if (kpiPressure) kpiPressure.innerText = '62,3% da renda';
                    if (kpiPressureBar) kpiPressureBar.style.width = '62.3%';
                }
            });
        });

        // 5. Transaction Feed Tab Filter
        const txTabs = document.querySelectorAll('.dz-tx-tab');
        txTabs.forEach(tab => {
            tab.addEventListener('click', function () {
                txTabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                const typeFilter = this.getAttribute('data-tx-type');

                txRows.forEach(row => {
                    const rowType = row.getAttribute('data-type');
                    const rowAccount = row.getAttribute('data-account-type');
                    if (typeFilter === 'all') {
                        row.style.display = 'flex';
                    } else if (typeFilter === 'expense' && rowType === 'expense') {
                        row.style.display = 'flex';
                    } else if (typeFilter === 'income' && rowType === 'income') {
                        row.style.display = 'flex';
                    } else if (typeFilter === 'credit_card' && rowAccount === 'credit_card') {
                        row.style.display = 'flex';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        });

        // 6. Live Search Filter in Prototype Transaction Feed
        const searchInput = document.getElementById('dz-search-tx');
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                const query = this.value.toLowerCase().trim();
                txRows.forEach(row => {
                    const text = row.innerText.toLowerCase();
                    if (!query || text.includes(query)) {
                        row.style.display = 'flex';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });

            document.addEventListener('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    searchInput.focus();
                }
            });
        }

        // 7. Interactive Concept Modal (Novo Lançamento)
        const modalOverlay = document.getElementById('dz-concept-modal');
        const openModalBtns = document.querySelectorAll('[data-open-concept-modal]');
        const closeModalBtns = document.querySelectorAll('[data-close-concept-modal]');

        function openModal(presetType = 'expense') {
            if (!modalOverlay) return;
            modalOverlay.classList.add('active');
            const typeRadios = modalOverlay.querySelectorAll('[data-modal-type]');
            typeRadios.forEach(btn => {
                btn.classList.toggle('active', btn.getAttribute('data-modal-type') === presetType);
            });
        }

        function closeModal() {
            if (!modalOverlay) return;
            modalOverlay.classList.remove('active');
        }

        openModalBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                const preset = this.getAttribute('data-open-concept-modal') || 'expense';
                openModal(preset);
            });
        });

        closeModalBtns.forEach(btn => {
            btn.addEventListener('click', closeModal);
        });

        if (modalOverlay) {
            modalOverlay.addEventListener('click', function (e) {
                if (e.target === modalOverlay) closeModal();
            });
        }

        const modalTypeBtns = document.querySelectorAll('[data-modal-type]');
        modalTypeBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                modalTypeBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
            });
        });

        const splitBtns = document.querySelectorAll('[data-split-option]');
        splitBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                splitBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
            });
        });

        const modalForm = document.getElementById('dz-concept-form');
        if (modalForm) {
            modalForm.addEventListener('submit', function (e) {
                e.preventDefault();
                closeModal();
                showConceptToast('Lançamento registrado com sucesso no protótipo!');
            });
        }
    }

    function showConceptToast(msg) {
        let toast = document.getElementById('dz-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'dz-toast';
            toast.style.cssText = `
                position: fixed;
                bottom: 24px;
                right: 24px;
                background: #10B981;
                color: #FFFFFF;
                padding: 12px 20px;
                border-radius: 9999px;
                font-size: 14px;
                font-weight: 700;
                box-shadow: 0 10px 25px rgba(16, 185, 129, 0.4);
                z-index: 9999;
                display: flex;
                align-items: center;
                gap: 8px;
                transform: translateY(100px);
                transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            `;
            document.body.appendChild(toast);
        }
        toast.innerHTML = `<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg> ${msg}`;
    }
    // 7. Mobile Drawer Toggle Handlers
    const drawer = document.getElementById('dz-mobile-drawer');
    const backdrop = document.getElementById('dz-drawer-backdrop');
    const openDrawerBtns = document.querySelectorAll('.js-dz-open-drawer');
    const closeDrawerBtns = document.querySelectorAll('.js-dz-close-drawer');

    function openDrawer() {
        if (drawer && backdrop) {
            drawer.classList.add('active');
            backdrop.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeDrawer() {
        if (drawer && backdrop) {
            drawer.classList.remove('active');
            backdrop.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    openDrawerBtns.forEach(btn => btn.addEventListener('click', openDrawer));
    closeDrawerBtns.forEach(btn => btn.addEventListener('click', closeDrawer));
    if (backdrop) {
        backdrop.addEventListener('click', closeDrawer);
    }
});
