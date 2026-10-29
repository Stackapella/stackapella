"""Share your result (js/result-card.js, components/share-result.js): the card made on the device at
both sizes (1080 by 1350 for a post, 1080 by 1920 for a story), handed to the device's share sheet
with the line and the lesson's link; where files can't be shared, the image downloads instead. The
sheet is mounted on its own with a real lesson and a three-star result, against the mock.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import t  # noqa: E402

MOUNT = """const { createApp, h } = await import('vue');
    const { t } = await import('/js/i18n.js');
    const store = await import('/js/stores/learning.js');
    await store.loadLearning();
    const entry = store.allLessons()[1];
    const { ShareResult } = await import('/js/components/share-result.js');
    const { localized } = await import('/js/i18n.js');
    const result = { stars: 3, percent: 100, word: 'results.word.brilliant', lit: 4, cents: 50,
        target: [{ pitch: 60, start: 0, end: 1 }, { pitch: 62, start: 1, end: 2 }, { pitch: 64, start: 2, end: 3 }, { pitch: 65, start: 3, end: 4 }],
        dots: Array.from({ length: 40 }, (_, i) => ({ t: i / 10, midi: [60, 62, 64, 65][Math.floor(i / 10)] + Math.sin(i) * 0.2, in: true })) };
    document.querySelector('#share-test')?.remove();
    const box = Object.assign(document.createElement('div'), { id: 'share-test' });
    document.body.prepend(box);
    const app = createApp({ render: () => h(ShareResult, { result, entry, lessonTitle: localized(entry.lesson.title), unitTitle: localized(entry.unit.title), color: '#c9b456' }) });
    app.config.globalProperties.t = t;
    app.mount(box);
    window.shareEntry = entry;"""

SIZE = "(async () => { const img = document.querySelector('[data-share-result] img'); const b = await createImageBitmap(await (await fetch(img.src)).blob()); return b.width + 'x' + b.height; })()"


def journey(f):
    f.go("/?app&api=mock&scenario=&as=member", "document.querySelector('#app header')")
    # A device that can share files.
    f.run("navigator.canShare = () => true; navigator.share = async (data) => { window.shared = data; };")
    f.run(MOUNT)
    f.click_t("results.share.button")
    f.wait("document.querySelector('[data-share-result] img')", what="the card, made")
    assert f.js(SIZE) == "1080x1350", f.js(SIZE)
    f.shot("post")
    f.click_t("results.share.story")
    f.wait("document.querySelector('[data-share-result] img')", what="the story card")
    assert f.js(SIZE) == "1080x1920", f.js(SIZE)
    f.shot("story")
    f.run("[...document.querySelectorAll('[data-share-result] button')].find((b) => b.textContent.trim() === " + json.dumps(t(f, "results.share.share")) + ").click()")
    f.wait("window.shared", what="the share sheet called")
    shared = json.loads(f.js("JSON.stringify({ files: window.shared.files.map((x) => [x.name, x.type, x.size]), text: window.shared.text, url: window.shared.url })"))
    assert shared["files"][0][1] == "image/png" and shared["files"][0][2] > 10000, shared
    lesson = f.js("(await import('/js/i18n.js')).localized(window.shareEntry.lesson.title)")
    # Game mode's line says the stars; serious mode's the measure (docs/skins-plan.md row 17).
    assert lesson in shared["text"] and ("3" in shared["text"] if f.skin == "game" else "%" in shared["text"]), shared["text"]
    assert shared["url"].endswith(f"/learn/{f.js('window.shareEntry.lesson.id')}"), f"the link opens the lesson for a newcomer: {shared['url']}"
    # No name or photo on it: the card's words are the result's, the lesson's and the brand's.
    # A desktop that can't share files: the image downloads, and the line can be copied.
    f.run("navigator.canShare = undefined; window.downloaded = null; HTMLAnchorElement.prototype.click = function () { if (this.download) window.downloaded = this.download; };")
    f.run(MOUNT)
    f.click_t("results.share.button")
    f.wait("document.querySelector('[data-share-result] img')", what="the card, made")
    f.wait_text(t(f, "results.share.copy"))
    f.click_t("results.share.download")
    f.wait("window.downloaded", what="the image downloaded")
    assert f.js("window.downloaded").endswith("-post.png"), f.js("window.downloaded")


if __name__ == "__main__":
    run("result_share", journey, sizes=("desktop",))
