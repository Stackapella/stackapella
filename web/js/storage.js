// localStorage and sessionStorage can throw (private windows, blocked storage). The app works
// without them: preferences just aren't remembered.

function safe(area) {
    return {
        get(key) {
            try {
                return area().getItem(key);
            } catch {
                return null;
            }
        },
        set(key, value) {
            try {
                area().setItem(key, value);
            } catch {
                // Not remembered.
            }
        },
        remove(key) {
            try {
                area().removeItem(key);
            } catch {
                // Nothing kept to remove.
            }
        },
    };
}

export const local = safe(() => window.localStorage);
export const session = safe(() => window.sessionStorage);
