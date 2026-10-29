# The library's next songs

Status: approved by Hillel (2026-10-29): "Two a month" (Steal Away, All Through the Night and Home on the Range first, Keep on the Sunny Side next) and "Yes, true barbershop" for Down by the Old Mill Stream

**What this is:** the next six songs to arrange for the library, so Plus keeps its promise of new
songs each month, in six different styles; plus two that the courses would use at once. Each one is
public domain in the US and the EU, its notes and words come from a public-domain printing (never a
modern arrangement), and each has a plan for 2, 3 and 4 singers built on the library's rules
(`docs/arrangements-voicings-plan.md`: the alto takes the third when the tune doesn't, the tenor a
moving line, no bare fourths or fifths on strong beats in the duet). Checked with courses-styles and
courses-harmony: none clashes with a course, and courses-harmony would use two of them.

## The six

| # | Song | Style | Words | Tune | Where the notes and words come from | US | EU |
|---|---|---|---|---|---|---|---|
| 1 | **Steal Away** | spiritual | traditional (attributed to Wallace Willis, before 1862) | traditional | Theodore Seward, *Jubilee Songs* (Fisk University, 1872), p. 28: read from the scan | published 1872, public domain | traditional; no living author |
| 2 | **Blow the Man Down** | sea shanty | traditional | traditional | W. B. Whall, *Ships, Sea Songs and Shanties* (3rd ed., 1913; Whall died 1917), and Cecil Sharp, *English Folk-Chanteys* (1914; Sharp died 1924) | published before 1931 | the editors died over 70 years ago |
| 3 | **All Through the Night** | lullaby | Harold Boulton, English (1884; died 1935) | Welsh, "Ar Hyd y Nos" (Edward Jones, *Musical and Poetical Relicks of the Welsh Bards*, 1784) | Boulton's words and the tune in *Songs of the Four Nations* (1892), No. 26: his own "Sleep my love", not the later "Sleep, my child" | published before 1931 | Boulton died over 70 years ago |
| 4 | **Now Is the Month of Maying** | madrigal | anonymous, after Orazio Vecchi | Thomas Morley (1595; died 1602) | Morley's *First Booke of Balletts to Five Voyces* (1595), read from the original or E. H. Fellowes's 1913 edition (Fellowes died 1951): never a modern CPDL or other edition, which has its own copyright | 1595 | 1595 |
| 5 | **Down by the Old Mill Stream** | barbershop | Tell Taylor (1910; died 1937) | Tell Taylor | the 1910 sheet music (Tell Taylor Music, Chicago), not a barbershop society arrangement | published 1910; its renewal ran out in 1966 | Taylor died 1937; Earl K. Smith, named co-composer in the renewal, died 1954, so public domain since 2025 |
| 6 | **Loch Lomond** | Scottish folk song | traditional | traditional | Finlay Dun and John Thomson's 1841 printing (Paterson and Roy, Edinburgh) | published 1841 | traditional |

**Two more the courses would use** (courses-harmony: "they would replace two tunes I'd otherwise
have to transcribe"):

| # | Song | Style | Words | Tune | Source | Status |
|---|---|---|---|---|---|---|
| 7 | **Home on the Range** | cowboy song | Brewster Higley (1872 or 1873; died 1909 or 1911) | Daniel Kelley (died 1905) | John Lomax, *Cowboy Songs* (1910; Lomax died 1948), whose first edition prints the tune | public domain in the US and the EU |
| 8 | **Keep on the Sunny Side** | gospel, bluegrass | Ada Blenkhorn (1899; died 1927) | J. Howard Entwisle (died 1903) | the 1899 hymnal printing | public domain in the US and the EU |

## Each one for 2, 3 and 4 singers

| Song | Key, metre, style | 2 singers | 3 singers | 4 singers |
|---|---|---|---|---|
| Steal Away | slow 4/4, around E-flat, free (vibrato welcome) | soprano and alto in thirds on "Steal away to Jesus", the alto answering "I ain't got long to stay here" | the bass on the roots under the refrain, humming through the verse | SATB, the refrain in close harmony; the verse soprano-led, the others answering |
| Blow the Man Down | lilting 3/4 or 6/8, around G, free | the soprano calls each line; soprano and alto sing "Way, hey, blow the man down" in thirds | the bass joins the answers on the roots | the tenor fills the answers; the calls stay solo, as at sea |
| All Through the Night | gentle 4/4, around G, straight | soprano and alto in thirds and sixths, as it's often sung | the bass under the harmony, softly | a hymn-like SATB, the tenor moving, the bass breathing with the lines |
| Now Is the Month of Maying | lively 4/4, around G, straight | soprano and alto keeping Morley's fa-la answers | soprano, alto and bass: the fa-las complete | SATB from Morley's five voices (the two tenors merged, keeping his imitations) |
| Down by the Old Mill Stream | 4/4, around B-flat, straight (to ring) | the lead and the baritone in thirds and sixths | the lead, baritone and bass: every chord with its third | barbershop: the tenor above the lead (see the question below), the sevenths complete |
| Loch Lomond | 4/4, around E-flat, free | soprano and alto in thirds, the alto taking the chorus's "Oh ye'll tak' the high road" a sixth below | the bass on the roots, a drone in places, Scottish style | SATB, the tenor echoing the tune in the chorus |

Each is checked with `app:templates:check` and the 2-to-4 rules before it lands, played at 2, 3
and 4 in the app, and its `reviewed` stays `false` until the musician (or the beta singers) pass it.

## Questions for Hillel

1. **The order:** one a month in the order above, or two a month as the earlier plan said
   (`docs/templates-plan.md`, "about two a month")? Recommended: two a month, starting with Steal
   Away and All Through the Night (the warmest for beginners).
2. **Down by the Old Mill Stream in true barbershop voicing**, with the tune in the second voice
   (the lead) and the tenor above it, as courses-harmony would use in its Barbershop course? The
   library's barbershop songs today put the tune on top as the soprano. True barbershop needs two
   part names the app doesn't word yet (lead and baritone), agreed with the frontend.
   Recommended: yes, for this song.
3. **Songs 7 and 8 for the courses:** arrange them too, after the six or between them?
   Recommended: Home on the Range with the first pair, since a course is waiting on it.

---

[Docs guide](../README.md#a-guide-to-the-docs)
