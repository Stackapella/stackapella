// Celebrating new stars (js/celebrate.js): 3 stars throw a bigger, faster burst than 2; fewer are
// not celebrated; the pieces start where the stars are and fly upward first.
import { LEVELS, pieces } from '/js/celebrate.js';
import { assert, test } from './harness.js';

test('celebrate: three stars throw more, faster, than two; one star nothing', () => {
    const two = pieces(2, { x: 100, y: 200, colors: ['red'] });
    const three = pieces(3, { x: 100, y: 200, colors: ['red'] });
    assert(two.length === LEVELS[2].pieces && three.length === LEVELS[3].pieces && three.length > two.length * 3, `${two.length} and ${three.length}`);
    const speed = (list) => list.reduce((s, p) => s + Math.hypot(p.vx, p.vy), 0) / list.length;
    assert(speed(three) > speed(two), 'faster');
    assert(pieces(1, { x: 0, y: 0, colors: ['red'] }).length === 0, 'one star: nothing');
    assert(three.every((p) => p.x === 100 && p.y === 200 && p.vy < 0), 'from the stars, upward first');
});
