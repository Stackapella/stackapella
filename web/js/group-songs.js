// A choir group's songs, from a singer's side (contract/api.md, "Choir groups"): the parts your
// directors have given you, across your groups, for Home's "Learn for your choir" band; and opening
// one, which goes back to your own copy of the song once you've begun it, on your part, rather than
// making a new copy at every tap.

import { api } from './api.js';
import { session as tabStorage } from './storage.js';
import * as db from './db.js';
import { loadTemplate } from './templates.js';
import { loadProjects, projects, saveProject, startFromTemplate, storedProject } from './stores/projects.js';

/** At most this many groups are opened for Home's band (a singer is rarely in more). */
const MOST_GROUPS = 4;

/**
 * The parts you've been given and haven't recorded yet, in the groups you sing in: newest given first.
 * Each is { groupId, groupName, templateId, title, partKey, status }. Empty for anyone in no group.
 */
export async function myGroupParts(meId, groups) {
    const singing = (groups ?? []).filter((g) => g.role === 'member' && g.songs > 0).slice(0, MOST_GROUPS);
    const shown = await Promise.all(singing.map((g) => api.groups.show(g.id).then((r) => r.data).catch((error) => {
        if (error?.code !== 'offline') console.warn('A choir group did not load', g.id, error);
        return null;
    })));
    return shown.filter(Boolean).flatMap((group) => {
        const me = group.members.find((m) => m.id === meId);
        return (me?.assignments ?? []).filter((a) => a.status !== 'recorded').map((a) => ({
            groupId: group.id,
            groupName: group.name,
            templateId: a.templateId,
            title: group.songs.find((s) => s.templateId === a.templateId)?.title ?? a.templateId,
            partKey: a.partKey,
            status: a.status,
            rehearsal: group.rehearsal ?? null,
        }));
    });
}

/** Your own copy of a song: the newest project made from it that's yours alone (not a friend's, a lesson's, the beta's or an idea's). */
export function ownCopy(templateId) {
    return projects.list
        .filter((p) => p.template?.id === templateId && !p.shared && !p.course && !p.beta && !p.idea)
        .sort((a, b) => (b.updatedAt ?? 0) - (a.updatedAt ?? 0))[0] ?? null;
}

/**
 * Opens a group's song (on your part, when you have one) and returns the project's id: your own copy
 * when there is one, else a new copy (no song limit since 2026-11-07: the free plan's limit is storage).
 */
export async function learnGroupSong({ templateId, partKey = null, status = 'none', template = null }) {
    if (!projects.loaded) await loadProjects();
    // A part given but not begun since: a copy from before isn't counted by the director's view, so a new one.
    const kept = partKey && status === 'none' ? null : ownCopy(templateId);
    if (kept) {
        if (partKey) {
            const project = await storedProject(kept.id);
            const slot = (await db.allBy('parts', 'projectId', kept.id)).find((p) => p.templatePartKey === partKey);
            if (project && slot && project.practice?.focus !== slot.id) await saveProject({ ...project, practice: { ...project.practice, focus: slot.id } });
        }
        fromGroup(kept.id);
        return kept.id;
    }
    const project = await startFromTemplate(template ?? (await loadTemplate(templateId)), { focusKey: partKey });
    fromGroup(project.id);
    return project.id;
}

/** This tab opened the song from a group: its first take is a group's (grp_sang, js/stores/session.js). */
export const fromGroup = (projectId) => tabStorage.set(`stackapella.fromGroup.${projectId}`, '1');
