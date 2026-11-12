import test from 'node:test';
import assert from 'node:assert/strict';
import { tableUrl } from '../../resources/js/admin-table.js';

test('prospecting columns retain their own visibility and list context without changing preregistration defaults', () => {
    const columns = ['business_name', 'priority', 'assigned_to', 'commercial_status'];
    const url = tableUrl('https://example.invalid/admin/prospeccao?city=Vassouras&page=2&blocked=yes', {
        visibility: { business_name: false, priority: true, assigned_to: false, commercial_status: true },
    }, columns);
    assert.deepEqual(url.searchParams.getAll('columns[]'), ['business_name', 'priority', 'commercial_status']);
    assert.equal(url.searchParams.get('blocked'), 'yes');
    assert.equal(url.searchParams.get('city'), 'Vassouras');
    assert.equal(url.searchParams.get('page'), '2');
    assert.equal(url.pathname, '/admin/prospeccao');
});

test('sorting preserves filters and resets page on the server URL', () => {
    const url = tableUrl('https://example.invalid/admin?q=Oficina&state=RJ&page=5&per_page=50', { sorting: [{ id: 'business_name', desc: false }] });
    assert.equal(url.searchParams.get('page'), '1');
    assert.equal(url.searchParams.get('q'), 'Oficina');
    assert.equal(url.searchParams.get('per_page'), '50');
    assert.equal(url.searchParams.get('direction'), 'asc');
});

test('pagination keeps search and column visibility retains mandatory record link', () => {
    const url = tableUrl('https://example.invalid/admin?q=Teste&columns[0]=category', {
        pagination: { pageIndex: 2, pageSize: 10 }, visibility: { business_name: false, category: true, city: false, state: false, status: true, created_at: false },
    });
    assert.equal(url.searchParams.get('page'), '3');
    assert.equal(url.searchParams.get('q'), 'Teste');
    assert.deepEqual(url.searchParams.getAll('columns[]'), ['business_name', 'category', 'status']);
    assert.equal(url.searchParams.has('columns[0]'), false);
});
