/**
 * Dawaam - High-Performance Data Table Client Helper
 * Implements 300ms search debouncing, AbortController request cancellation,
 * non-blocking loading overlays, and error state handling.
 */

(function() {
    'use strict';

    window.DawaamDataTable = {
        activeController: null,

        /**
         * Create a debounced function wrapper
         */
        debounce: function(func, wait) {
            let timeout;
            return function(...args) {
                const context = this;
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(context, args), wait || 300);
            };
        },

        /**
         * Attach server-side debounced search handler to an input form
         */
        attachDebouncedSearch: function(inputSelector, formSelector, delay) {
            const inputEl = document.querySelector(inputSelector);
            const formEl = document.querySelector(formSelector);

            if (!inputEl || !formEl) return;

            const handleSearchSubmit = this.debounce(function() {
                // Cancel previous pending request if any
                if (window.DawaamDataTable.activeController) {
                    window.DawaamDataTable.activeController.abort();
                }
                window.DawaamDataTable.activeController = new AbortController();

                // Trigger form submission
                if (typeof formEl.requestSubmit === 'function') {
                    formEl.requestSubmit();
                } else {
                    formEl.submit();
                }
            }, delay || 300);

            inputEl.addEventListener('input', function(e) {
                // Ignore empty or short searches if desired, or trigger debounced submit
                handleSearchSubmit();
            });
        },

        /**
         * Show non-blocking loading spinner / overlay on a table container
         */
        showLoadingState: function(containerSelector) {
            const container = document.querySelector(containerSelector);
            if (!container) return;

            container.style.position = 'relative';
            container.style.opacity = '0.6';

            let overlay = container.querySelector('.dw-table-loading-overlay');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.className = 'dw-table-loading-overlay position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-white bg-opacity-75 z-3';
                overlay.innerHTML = '<div class="spinner-border text-emerald" role="status"><span class="visually-hidden">Loading...</span></div>';
                container.appendChild(overlay);
            }
        },

        /**
         * Remove loading state
         */
        hideLoadingState: function(containerSelector) {
            const container = document.querySelector(containerSelector);
            if (!container) return;

            container.style.opacity = '1';
            const overlay = container.querySelector('.dw-table-loading-overlay');
            if (overlay) {
                overlay.remove();
            }
        }
    };
})();
