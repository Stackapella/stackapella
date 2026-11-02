// Whether api.js answers from the mock (js/api-mock.js), on its own so production's pages never
// download the mock to ask (a first visit's lesson 1 included: tests/flows/slow_phone.py).

import { session as tabStorage } from './storage.js';

const THIS_MACHINE = () => ['localhost', '127.0.0.1'].includes(location.hostname) || location.hostname.endsWith('.test');

/** Whether calls go to the mock: asked for with ?api=mock, on this machine only. */
export function mockWanted() {
    if (!THIS_MACHINE()) return false;
    const params = new URLSearchParams(location.search);
    if (params.has('api')) tabStorage.set('stackapella.api', params.get('api') === 'mock' ? 'mock' : '');
    if (params.has('scenario')) tabStorage.set('stackapella.scenario', params.get('scenario'));
    if (params.has('as')) tabStorage.set('stackapella.mockAs', params.get('as'));
    return tabStorage.get('stackapella.api') === 'mock';
}
