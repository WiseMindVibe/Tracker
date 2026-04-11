document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('.notifications-page');
    if (!root) {
        return;
    }

    const searchInput = document.getElementById('notif-search');
    const searchForm = searchInput && searchInput.form ? searchInput.form : null;

    if (searchForm && searchInput) {
        searchForm.addEventListener('submit', () => {
            searchInput.value = searchInput.value.trim();
        });
    }

    // Keep disabled pagination links inert if clicked by script or keyboard.
    root.querySelectorAll('a[aria-disabled="true"]').forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
        });
    });

    if (searchInput) {
        searchInput.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }
            if (searchInput.value.trim() !== '') {
                searchInput.value = '';
                event.preventDefault();
                return;
            }
            searchInput.blur();
        });
    }

    document.addEventListener('keydown', (event) => {
        if (!searchInput || event.defaultPrevented) {
            return;
        }
        if (event.key !== '/') {
            return;
        }
        if (isTypingContext(event.target)) {
            return;
        }

        event.preventDefault();
        searchInput.focus();
        searchInput.select();
    });
});

/**
 * @param {EventTarget|null} target
 * @returns {boolean}
 */
function isTypingContext(target) {
    if (!(target instanceof Element)) {
        return false;
    }
    if (target.closest('input, textarea, select')) {
        return true;
    }

    return Boolean(target.closest('[contenteditable=""], [contenteditable="true"]'));
}
