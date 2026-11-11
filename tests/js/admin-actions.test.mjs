import test from 'node:test';
import assert from 'node:assert/strict';
import { actionNeedsReason } from '../../resources/js/admin-actions.js';

test('rejection and archiving require reason while approval and reopening do not', () => {
    assert.equal(actionNeedsReason('rejected'), true);
    assert.equal(actionNeedsReason('archived'), true);
    assert.equal(actionNeedsReason('approved'), false);
    assert.equal(actionNeedsReason('under_review'), false);
});
