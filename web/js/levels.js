// How loud a published guide is (server/app/Jobs/PublishGuide.php): a take is levelled to about
// -20 dBFS (its mean, as ffmpeg's volumedetect measures it), within 24 dB either way, and never
// louder than -2 dBFS at its loudest sample. Shared by the review's preview and "Hear it as
// learners will", so both sound as learners will hear it.

const LEVEL_DBFS = -20;

/** The mean loudness (RMS over the whole take), in dBFS. */
export function rmsDbfs(samples) {
    let sum = 0;
    for (let i = 0; i < samples.length; i++) sum += samples[i] * samples[i];
    return sum > 0 ? 10 * Math.log10(sum / samples.length) : -Infinity;
}

/** The loudest sample, in dBFS. */
export function peakDbfs(samples) {
    let peak = 0;
    for (let i = 0; i < samples.length; i++) peak = Math.max(peak, Math.abs(samples[i]));
    return peak > 0 ? 20 * Math.log10(peak) : -Infinity;
}

/** The gain a take at `loudnessDbfs` gets when published, plus a reviewer's `gainDb`. */
export function guideGain(loudnessDbfs, gainDb = 0, peak = null) {
    const levelling = Number.isFinite(loudnessDbfs) ? LEVEL_DBFS - loudnessDbfs : 0;
    let db = Math.max(-24, Math.min(24, levelling + gainDb));
    if (Number.isFinite(peak)) db = Math.min(db, -2 - peak);
    return 10 ** (db / 20);
}
