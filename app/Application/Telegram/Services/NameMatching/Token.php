<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching;

use App\Application\Telegram\Services\NameMatching\Roles\NameRole;
use App\Application\Telegram\Services\NameMatching\Support\NameAffixStripper;
use App\Application\Telegram\Services\NameMatching\Support\OrthographicVariantFolder;
use App\Application\Telegram\Services\NameMatching\Support\PhoneticKeyBuilder;

/**
 * One normalized name/username fragment, in the four forms the
 * comparison tiers need.
 *
 * Each form throws away a little more than the previous one, and each
 * one exists because a comparator needs exactly that much thrown away:
 *
 *  - {@see self::$display}   the spelling an operator should see
 *                            (accents/emoji/styling removed, real
 *                            spelling preserved) -- IKRAMZHON;
 *  - {@see self::$canonical} spelling variance folded away
 *                            ({@see OrthographicVariantFolder}) so two
 *                            romanizations of one name meet -- ikramjon;
 *  - {@see self::$core}      the identity root, honorific affixes
 *                            removed ({@see NameAffixStripper}) --
 *                            abdulkodir and kodirali both give kodir;
 *  - {@see self::$phonetic}  the vowel choices folded away too
 *                            ({@see PhoneticKeyBuilder}) -- odil and
 *                            adil both give adil.
 *
 * Only $display is ever shown. The others are comparison forms and are
 * deliberately unreadable.
 *
 * A driver-side token additionally carries its {@see NameRole}: which
 * part of the full name it is, so the scoring layer can tell a matched
 * given name (the part a Telegram profile actually shows) from a
 * matched surname or patronymic.
 */
final class Token
{
    public readonly string $canonical;

    public readonly string $core;

    public readonly string $phonetic;

    /**
     * Phonetic key of the affix-free core: the loosest form of all, used
     * only by the tier that has to reconcile a different affix AND a
     * different vowel at once (ADILKHAN <-> Odilxon).
     */
    public readonly string $coreKey;

    public function __construct(
        public readonly string $display,
        public readonly NameRole $role = NameRole::Unknown,
        ?OrthographicVariantFolder $folder = null,
        ?NameAffixStripper $affixStripper = null,
        ?PhoneticKeyBuilder $phoneticKeyBuilder = null,
    ) {
        $folder ??= new OrthographicVariantFolder;
        $affixStripper ??= new NameAffixStripper;
        $phoneticKeyBuilder ??= new PhoneticKeyBuilder;

        $this->canonical = $folder->foldCore($display);
        $this->core = $affixStripper->core($this->canonical);
        $this->phonetic = $phoneticKeyBuilder->build($this->canonical);
        $this->coreKey = $phoneticKeyBuilder->build($this->core);
    }

    /**
     * The same token seen as a particular part of the full name.
     */
    public function withRole(NameRole $role): self
    {
        return $role === $this->role
            ? $this
            : new self($this->display, $role);
    }

    public function hasAffix(): bool
    {
        return $this->core !== $this->canonical;
    }

    public function length(): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($this->display, 'UTF-8')
            : strlen($this->display);
    }

    public function displayUpper(): string
    {
        return function_exists('mb_strtoupper')
            ? mb_strtoupper($this->display, 'UTF-8')
            : strtoupper($this->display);
    }
}
