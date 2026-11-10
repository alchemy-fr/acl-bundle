document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('.acl-permissions');
    if (!root) {
        return;
    }

    const tbody = root.querySelector('tbody');
    const rowTemplate = root.querySelector('template[data-acl-row-template]');
    const objectType = root.dataset.objectType;
    const objectId = root.dataset.objectId || null;

    function aceKey(row) {
        return {
            objectType,
            objectId,
            userType: row.dataset.aclUserType,
            userId: row.dataset.aclUserId,
        };
    }

    function send(method, body) {
        return fetch(root.dataset.aceUrl, {
            method,
            headers: {'Content-Type': 'application/json', Accept: 'application/json'},
            body: JSON.stringify(body),
        });
    }

    function syncAllToggle(row) {
        const boxes = [...row.querySelectorAll('[data-acl-permission]')];
        const checked = boxes.filter(box => box.checked).length;
        const toggle = row.querySelector('[data-acl-all]');
        if (checked === boxes.length) {
            toggle.checked = true;
        } else if (0 === checked) {
            toggle.checked = false;
        }
    }

    // The mask keeps the bits of the permissions which are not shown
    function saveMask(row) {
        let mask = parseInt(row.dataset.aclMask || '0', 10);
        row.querySelectorAll('[data-acl-permission]').forEach(box => {
            const bit = parseInt(box.value, 10);
            mask = box.checked ? mask | bit : mask & ~bit;
        });
        row.dataset.aclMask = mask;

        return send('PUT', {...aceKey(row), mask});
    }

    function isGranted(userType, userId) {
        return [...tbody.querySelectorAll('tr:not(.acl-inherited)')].some(row => row.dataset.aclUserType === userType
            && row.dataset.aclUserId === userId);
    }

    root.addEventListener('change', event => {
        const input = event.target;
        const row = input.closest('tbody tr');
        if (!row) {
            return;
        }

        if (input.matches('[data-acl-all]')) {
            row.querySelectorAll('[data-acl-permission]').forEach(box => box.checked = input.checked);
        }
        syncAllToggle(row);
        saveMask(row);
    });

    root.addEventListener('click', event => {
        const button = event.target.closest('[data-acl-delete]');
        const row = button?.closest('tbody tr');
        if (!row) {
            return;
        }

        row.remove();
        send('DELETE', aceKey(row));
    });

    root.querySelectorAll('select[data-acl-user-type]').forEach(select => {
        select.addEventListener('change', () => {
            const userType = select.dataset.aclUserType;
            const userId = select.value;
            if (!userId) {
                return;
            }
            const option = select.selectedOptions[0];
            if (select.tomselect) {
                select.tomselect.clear(true);
            } else {
                select.value = '';
            }
            if (isGranted(userType, userId)) {
                return;
            }

            const row = rowTemplate.content.firstElementChild.cloneNode(true);
            row.dataset.aclUserType = userType;
            row.dataset.aclUserId = userId;
            row.classList.add(`acl-${userType}`);
            if (userId === root.dataset.userWildcard) {
                row.classList.add('acl-wildcard');
            }
            row.querySelector('.acl-name').textContent = option.textContent;

            tbody.prepend(row);
            send('PUT', {...aceKey(row), mask: 0});
        });
    });

    tbody.querySelectorAll('tr').forEach(syncAllToggle);
});
