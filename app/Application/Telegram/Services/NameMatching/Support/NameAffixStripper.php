<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching\Support;

/**
 * Strips the decorative name-building affixes of Uzbek/Central Asian
 * names, leaving the identity-carrying core.
 *
 * Uzbek given names are largely built by gluing an honorific or
 * affectionate element onto a root, and which elements a person keeps
 * is a matter of taste that changes between the passport and the
 * Telegram profile of the very same person:
 *
 *   ABDULKODIR (passport)  ->  Qodirali   (Telegram)
 *   ELYOR      (passport)  ->  Elyorbek   (Telegram)
 *   ADILKHAN   (passport)  ->  Odilxon    (Telegram)
 *
 * Nothing in the spelling itself is wrong in any of these -- the two
 * spellings simply carry a different set of affixes around the same
 * root, so comparing the roots is what answers "is this the same name".
 *
 * Two deliberate limits keep this from turning into a false-positive
 * machine:
 *
 *  - only ONE prefix and ONE suffix are ever removed, so a name is never
 *    whittled down to an arbitrary fragment;
 *  - the core that remains must still be at least
 *    {@see self::MIN_CORE_LENGTH} characters, which is what stops "ALI"
 *    from being stripped to nothing and keeps a genuinely short name
 *    (BEK, JON) intact as itself.
 *
 * Surname endings (-ov, -ev, -boyev, ...) are intentionally NOT listed:
 * they are shared by unrelated families rather than by spellings of one
 * name, and removing them is exactly how a matcher starts pairing
 * ALIYEV with KARIMOV.
 *
 * Input and output are {@see OrthographicVariantFolder} canonical forms
 * (so "xon"/"khan"/"hon" have already collapsed onto "hon"/"han" and "q"
 * onto "k"), never display spellings.
 */
final class NameAffixStripper
{
    /**
     * Shortest core worth keeping. Four characters is what separates a
     * root ("odil", "kodir", "elyor") from a fragment.
     */
    private const MIN_CORE_LENGTH = 4;

    /**
     * Honorific / affectionate elements appended to a root, written in
     * canonical (folded) form.
     *
     * "bek" (lord), "jon" (dear), "hon"/"han" (khan), "boy"/"bay" (rich),
     * "ali", "murod" and "berdi" are the ones that actually recur in this
     * dataset.
     */
    private const SUFFIXES = [
        'bek', 'bik', 'jon', 'jan', 'hon', 'han', 'boy', 'bay',
        'ali', 'berdi', 'murod', 'murad',
    ];

    /**
     * Elements prepended to a root. "abdul"/"abdu" (servant of),
     * "mirza"/"mir" (prince), "sher" (lion) -- all of them routinely
     * dropped when the person introduces themselves.
     *
     * The shorter "abd" is deliberately absent: it would cut ABDULLA,
     * a name in its own right, down to the non-root "ulla", while the
     * names it would help with are already covered by the two longer
     * spellings.
     */
    private const PREFIXES = [
        'abdul', 'abdu', 'mirza', 'mir', 'sher',
    ];

    /**
     * The core of a canonical token: at most one prefix and one suffix
     * removed. Returns the token unchanged when nothing can be removed
     * without cutting into the root.
     */
    public function core(string $canonical): string
    {
        $core = $this->stripPrefix($canonical);

        return $this->stripSuffix($core);
    }

    /**
     * Whether stripping actually changed anything -- the caller needs
     * this to know that it is looking at affix-tolerant evidence rather
     * than at a plain equality.
     */
    public function hasAffix(string $canonical): bool
    {
        return $this->core($canonical) !== $canonical;
    }

    private function stripPrefix(string $token): string
    {
        foreach (self::PREFIXES as $prefix) {
            if (! str_starts_with($token, $prefix)) {
                continue;
            }

            $core = substr($token, strlen($prefix));

            if (strlen($core) >= self::MIN_CORE_LENGTH) {
                return $core;
            }
        }

        return $token;
    }

    private function stripSuffix(string $token): string
    {
        foreach (self::SUFFIXES as $suffix) {
            if (! str_ends_with($token, $suffix)) {
                continue;
            }

            $core = substr($token, 0, -strlen($suffix));

            if (strlen($core) >= self::MIN_CORE_LENGTH) {
                return $core;
            }
        }

        return $token;
    }
}
