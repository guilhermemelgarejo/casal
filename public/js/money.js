/* Máscara compartilhada: exibição em reais e envio em decimal para o Laravel. */
(() => {
    'use strict';

    const selector = 'input[data-money]';
    const precision = (input) => Number(input.dataset.moneyPrecision || 2);

    const normalize = (raw, decimals = 2) => {
        let value = String(raw ?? '').replace(/[^\d.,-]/g, '');
        if (!/\d/.test(value)) return '';
        const negative = value.startsWith('-');
        value = value.replace(/-/g, '');
        const comma = value.lastIndexOf(',');
        const dot = value.lastIndexOf('.');
        if (comma > dot) {
            value = value.replace(/\./g, '').replace(',', '.');
        } else if (comma !== -1) {
            value = value.replace(/,/g, '');
        } else if (decimals === 2 && /^\d{1,3}(\.\d{3})+$/.test(value)) {
            value = value.replace(/\./g, '');
        }
        const [whole, fraction = ''] = value.split('.');
        return `${negative ? '-' : ''}${(whole || '0').replace(/^0+(?=\d)/, '')}.${fraction.slice(0, decimals).padEnd(2, '0')}`;
    };

    const format = (raw, decimals = 2) => {
        const value = normalize(raw, decimals);
        if (value === '') return '';
        const [whole, fraction] = value.split('.');
        return `${whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.')},${fraction}`;
    };

    const validate = (input) => {
        const value = normalize(input.value, precision(input));
        let message = '';
        if (value !== '') {
            if (input.dataset.moneyMin != null && Number(value) < Number(input.dataset.moneyMin)) {
                message = `Informe um valor maior ou igual a ${format(input.dataset.moneyMin)}.`;
            } else if (input.dataset.moneyMax != null && Number(value) > Number(input.dataset.moneyMax)) {
                message = `Informe um valor menor ou igual a ${format(input.dataset.moneyMax)}.`;
            }
        }
        input.setCustomValidity(message);
    };

    const refresh = (root = document, excluded = null) => {
        const inputs = Array.from(root.querySelectorAll(selector));
        if (root.matches?.(selector)) inputs.unshift(root);
        inputs.forEach((input) => {
            if (input === excluded) return;
            input.value = format(input.value, precision(input));
            validate(input);
        });
    };

    window.DuozenMoney = { format, normalize, refresh };

    // Captura antes dos cálculos de parcelas, rateios e simulações da página.
    document.addEventListener('input', (event) => {
        const input = event.target;
        if (!input.matches?.(selector)) {
            if (input.form) queueMicrotask(() => refresh(input.form));
            return;
        }
        const caret = input.selectionStart;
        const digitsAfter = input.value.slice(caret).replace(/\D/g, '').length;
        if (precision(input) === 2) {
            const negative = input.value.trim().startsWith('-');
            const digits = input.value.replace(/\D/g, '');
            input.value = digits ? format(`${negative ? '-' : ''}${digits.padStart(3, '0').slice(0, -2)}.${digits.padStart(3, '0').slice(-2)}`) : '';
        } else {
            // Cotações podem precisar de mais de duas casas decimais.
            const raw = input.value;
            const numeric = normalize(raw, precision(input));
            const separator = raw.lastIndexOf(',') > raw.lastIndexOf('.') ? ',' : '.';
            const whole = numeric.split('.')[0] || '0';
            if (raw.endsWith(separator)) input.value = whole + ',';
            else if (numeric && raw.includes(separator)) {
                const length = raw.slice(raw.lastIndexOf(separator) + 1).replace(/\D/g, '').length;
                input.value = whole + ',' + numeric.split('.')[1].slice(0, length);
            } else input.value = numeric ? whole : '';
        }
        let position = input.value.length;
        let remaining = digitsAfter;
        while (position > 0 && remaining > 0) {
            position -= 1;
            if (/\d/.test(input.value[position])) remaining -= 1;
        }
        input.setSelectionRange(position, position);
        validate(input);
        queueMicrotask(() => refresh(input.form || document, input));
    }, true);

    document.addEventListener('paste', (event) => {
        const input = event.target;
        if (!input.matches?.(selector) || !event.clipboardData) return;
        const pasted = event.clipboardData.getData('text');
        if (!/\d/.test(pasted)) return;
        event.preventDefault();
        input.value = format(pasted, precision(input));
        // O evento input aplica a máscara de centavos e dispara os cálculos existentes.
        input.dispatchEvent(new Event('input', { bubbles: true }));
    });

    document.addEventListener('change', (event) => {
        if (event.target.matches?.(selector)) refresh(event.target);
        queueMicrotask(() => refresh(event.target.form || document));
    }, true);
    document.addEventListener('blur', (event) => {
        if (event.target.matches?.(selector)) refresh(event.target);
    }, true);
    document.addEventListener('shown.bs.modal', (event) => refresh(event.target));
    document.addEventListener('reset', (event) => queueMicrotask(() => refresh(event.target)));

    // Também cobre new FormData(form), pré-verificações AJAX e form.submit().
    // Não altera a exibição enquanto o formulário aguarda confirmação ou resposta.
    document.addEventListener('formdata', (event) => {
        event.target.querySelectorAll(selector).forEach((input) => {
            if (!input.name || input.matches(':disabled') || !event.formData.has(input.name)) return;
            event.formData.set(input.name, normalize(input.value, precision(input)));
        });
    });

    document.addEventListener('DOMContentLoaded', () => {
        refresh();
        new MutationObserver((records) => {
            records.forEach((record) => record.addedNodes.forEach((node) => {
                if (node.nodeType === 1) refresh(node);
            }));
        }).observe(document.body, { childList: true, subtree: true });
    });
})();
