document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('.acl-permissions');
    if (!root) {
        return;
    }

    const tbody = root.querySelector('tbody');
    const rowTemplate = root.querySelector('template[data-acl-row-template]');
    const errorBox = root.querySelector('[data-acl-error]');
    const objectType = root.dataset.objectType;
    const objectId = root.dataset.objectId || null;

    function aceKey(row) {
        return {
            objectType,
            objectId,
            userType: row.dataset.aclUserType,
            userId: row.dataset.aclUserId,
            parentId: row.dataset.aclParentId || null,
        };
    }

    // Each PUT carries a whole mask: the requests are sent one after the other, so that
    // the last change is the last one written
    let queue = Promise.resolve();

    function send(method, body) {
        const request = queue.then(() => doSend(method, body));
        queue = request.catch(() => {});

        return request;
    }

    async function doSend(method, body) {
        errorBox.classList.add('d-none');
        try {
            const response = await fetch(root.dataset.aceUrl, {
                method,
                headers: {'Content-Type': 'application/json', Accept: 'application/json'},
                body: JSON.stringify(body),
            });
            if (!response.ok) {
                throw new Error(`${response.status} ${response.statusText}`);
            }
        } catch (e) {
            errorBox.textContent = `The permissions could not be saved (${e.message}), reload the page to see their current state.`;
            errorBox.classList.remove('d-none');

            throw e;
        }
    }

    function syncAllToggle(row) {
        const boxes = [...row.querySelectorAll('[data-acl-permission]')];
        const checked = boxes.filter(box => box.checked).length;
        const toggle = row.querySelector('[data-acl-all]');
        toggle.checked = checked === boxes.length;
        toggle.indeterminate = checked > 0 && checked < boxes.length;
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

    function setGrantable(userType, userId, grantable) {
        const select = root.querySelector(`select[data-acl-user-type="${userType}"]`);
        const option = [...select.options].find(o => o.value === userId);
        if (option) {
            option.disabled = !grantable;
        }
        // TomSelect keeps its own copy of the options
        const ts = select.tomselect;
        if (ts?.options[userId]) {
            ts.updateOption(userId, {...ts.options[userId], disabled: !grantable});
        }
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
        saveMask(row).catch(() => {});
    });

    root.addEventListener('click', event => {
        const button = event.target.closest('[data-acl-delete]');
        const row = button?.closest('tbody tr');
        if (!row) {
            return;
        }

        button.disabled = true;
        const key = aceKey(row);
        send('DELETE', key).then(() => {
            row.remove();
            if (null === key.parentId) {
                setGrantable(key.userType, key.userId, true);
            }
        }, () => button.disabled = false);
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
            if (option.disabled) {
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

            send('PUT', {...aceKey(row), mask: 0}).then(() => {
                tbody.prepend(row);
                setGrantable(userType, userId, false);
            }, () => {});
        });
    });

    tbody.querySelectorAll('tr').forEach(syncAllToggle);
});
