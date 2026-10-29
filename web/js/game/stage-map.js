// Stage select on a map (docs/game-elements-plan.md; the proposal's board 07, after Super Mario
// World's overworld and Sonic 2's special stage): the open unit's lessons as nodes on a winding
// dotted path, the dots lit in the unit's colour up to the lesson you're on, "You are here" over it
// (a slow pulse, still with reduced motion), and the unit's challenge as a special stage with its
// two-star target said first ("70% on pitch for two stars", the course's own rule). The other units
// stay a list. Picking a node works as the list's does: the course map shows its row underneath.

import { formatNumber, localized } from '../i18n.js';
import { starsFor } from '../course.js';
import { Icon } from '../components/icon.js';
import { cue } from './chip.js';

const ROW = 104; // px between nodes, top to bottom
const XS = [20, 72, 80, 28]; // percent across: a snake down the map

/** Where node `i` sits: `{ x }` percent across, `{ y }` px down. */
export const nodeAt = (i) => ({ x: XS[i % XS.length], y: 44 + i * ROW });

/** The path's x for a node: mirrored right to left, where the nodes (placed with inset-inline-start) mirror too. */
export const pathX = (x, rtl = document.documentElement.dir === 'rtl') => (rtl ? 100 - x : x);

/** The path between two nodes, in the map's own units (x 0 to 100, y px). */
export function segment(a, b, rtl) {
    const mid = (a.y + b.y) / 2;
    const [ax, bx] = [pathX(a.x, rtl), pathX(b.x, rtl)];
    return `M ${ax} ${a.y} C ${ax} ${mid}, ${bx} ${mid}, ${bx} ${b.y}`;
}

/** The least percent on pitch that earns two stars (the course's rule, not a number of our own). */
export function twoStarPercent() {
    for (let percent = 0; percent <= 100; percent++) if (starsFor({ percent }) >= 2) return percent;
    return 100;
}

export const StageMap = {
    components: { Icon },
    // nodes: [{ id, title, stars, current, picked, locked, challenge }]
    props: { nodes: { type: Array, required: true }, colour: { type: String, required: true } },
    emits: ['pick'],
    setup: () => ({ localized, formatNumber, nodeAt, segment, twoStarPercent }),
    methods: {
        /** A node picked: the cursor's blip (Game sounds only), and the course map shows its row. */
        pick(id) {
            cue('cursor');
            this.$emit('pick', id);
        },
    },
    template: `
        <div class="ge-map" :style="{ height: (nodeAt(nodes.length - 1).y + 76) + 'px', '--ge-unit': colour }" data-game-map>
            <svg class="ge-map-path" :viewBox="'0 0 100 ' + (nodeAt(nodes.length - 1).y + 76)" preserveAspectRatio="none" aria-hidden="true">
                <path v-for="(node, i) in nodes.slice(1)" :key="node.id" :d="segment(nodeAt(i), nodeAt(i + 1))" :class="nodes[i].stars ? 'is-lit' : ''" vector-effect="non-scaling-stroke" />
            </svg>
            <ol :aria-label="t('home.lead.path')">
                <li v-for="(node, i) in nodes" :key="node.id" class="ge-map-stop" :class="nodeAt(i).x > 50 ? 'is-end' : ''" :style="{ insetInlineStart: nodeAt(i).x + '%', top: nodeAt(i).y + 'px' }">
                    <span v-if="node.current" class="ge-map-here" aria-hidden="true">{{ t('game.map.here') }}</span>
                    <button type="button" class="ge-map-node" :class="[node.stars ? 'is-done' : '', node.current ? 'is-now' : '', node.challenge ? 'is-special' : '', node.picked ? 'is-picked' : '']" :disabled="node.locked"
                        :aria-label="t('game.map.node', { number: formatNumber(i + 1), label: node.locked ? t('game.map.locked', { title: localized(node.title) }) : t('course.lessonLabel', { title: localized(node.title), stars: node.stars }) })" :aria-current="node.current ? 'step' : null" :aria-pressed="node.picked ? 'true' : 'false'" @click="pick(node.id)">
                        <Icon v-if="node.stars" name="check" :size="20" />
                        <Icon v-else-if="node.challenge" name="starFilled" :size="18" class="ge-map-star" />
                        <span v-else class="ge-map-num">{{ formatNumber(i + 1) }}</span>
                    </button>
                    <span class="ge-map-label">
                        <span v-if="node.stars" class="ge-map-stars" aria-hidden="true"><i v-for="s in 3" :key="s" :class="s <= node.stars ? 'is-on' : ''">★</i></span>
                        <span v-if="node.challenge" class="ge-map-special">{{ t('game.map.special') }}</span>
                        <span class="ge-map-title" translate="no">{{ localized(node.title) }}</span>
                        <span v-if="node.challenge && node.stars < 2" class="ge-map-target">{{ t('game.map.target', { percent: twoStarPercent() }) }}</span>
                    </span>
                </li>
            </ol>
        </div>`,
};
