<?php

declare(strict_types=1);

namespace GDPE\Presentation\Shortcodes;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use GDPE\Infrastructure\Config\JetEngineFieldMap;

/**
 * Registers the [gdpe_age] shortcode.
 *
 * Detects the current dog post automatically, reads its birth date
 * from the JetEngine meta field, and renders the age as a full
 * years/months/days breakdown, e.g.:
 *
 *  - "4 years, 7 months, and 4 days"
 *  - "8 months and 12 days"
 *  - "12 days"
 *
 * Zero-valued units are omitted (a dog exactly two years old renders
 * as "2 years", not "2 years, 0 months, and 0 days"). Outputs an
 * empty string only when no birth date is available.
 */
final class AgeShortcode
{
    public const TAG = 'gdpe_age';

    public function register(): void
    {
        add_shortcode(self::TAG, [$this, 'render']);
    }

    /**
     * Shortcode callback.
     *
     * @param array<string, string>|string $attributes Raw shortcode attributes.
     */
    public function render(array|string $attributes = []): string
    {
        $postId = $this->resolveCurrentPostId();

        if ($postId === 0) {
            return '';
        }

        $birthDate = $this->resolveBirthDate($postId);

        if ($birthDate === null) {
            return '';
        }

        return esc_html($this->formatAge($birthDate, $this->today()));
    }

    /**
     * Resolves the dog post the shortcode is rendered for: the current
     * loop post first (covers Elementor loops and JetEngine listings),
     * falling back to the main queried object on singular pages.
     */
    private function resolveCurrentPostId(): int
    {
        $postId = get_the_ID();

        if (is_int($postId) && $postId > 0) {
            return $postId;
        }

        $queriedId = get_queried_object_id();

        return $queriedId > 0 ? $queriedId : 0;
    }

    /**
     * Reads and parses the birth date meta value. JetEngine date fields
     * may store either a Unix timestamp or a date string, so both are
     * accepted.
     */
    private function resolveBirthDate(int $postId): ?DateTimeImmutable
    {
        $raw = get_post_meta($postId, JetEngineFieldMap::META_BIRTH_DATE, true);

        if (!is_string($raw) && !is_int($raw)) {
            return null;
        }

        $raw = is_string($raw) ? trim($raw) : $raw;

        if ($raw === '' || $raw === 0) {
            return null;
        }

        $timezone = $this->timezone();

        try {
            if (is_int($raw) || ctype_digit($raw)) {
                return (new DateTimeImmutable('@' . $raw))->setTimezone($timezone);
            }

            return new DateTimeImmutable($raw, $timezone);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Formats the age as a natural-language breakdown of years, months
     * and days, omitting zero-valued units.
     */
    private function formatAge(DateTimeImmutable $birthDate, DateTimeImmutable $today): string
    {
        if ($birthDate > $today) {
            return $this->formatDays(0);
        }

        $interval = $birthDate->diff($today);

        $parts = [];

        if ($interval->y >= 1) {
            $parts[] = sprintf(
                /* translators: %d: number of whole years. */
                _n('%d year', '%d years', $interval->y, 'gdpe'),
                $interval->y
            );
        }

        if ($interval->m >= 1) {
            $parts[] = sprintf(
                /* translators: %d: number of whole months. */
                _n('%d month', '%d months', $interval->m, 'gdpe'),
                $interval->m
            );
        }

        if ($interval->d >= 1) {
            $parts[] = $this->formatDays($interval->d);
        }

        if ($parts === []) {
            return $this->formatDays(0);
        }

        return $this->joinParts($parts);
    }

    /**
     * Joins the age parts into a natural-language list:
     * one part as-is, two parts with "and", three parts with a
     * serial comma ("X, Y, and Z").
     *
     * @param list<string> $parts Non-empty list of formatted units.
     */
    private function joinParts(array $parts): string
    {
        $and = __('and', 'gdpe');

        return match (count($parts)) {
            1 => $parts[0],
            2 => $parts[0] . ' ' . $and . ' ' . $parts[1],
            default => implode(', ', array_slice($parts, 0, -1))
                . ', ' . $and . ' ' . $parts[count($parts) - 1],
        };
    }

    private function formatDays(int $days): string
    {
        return sprintf(
            /* translators: %d: number of days. */
            _n('%d day', '%d days', $days, 'gdpe'),
            $days
        );
    }

    private function today(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->timezone());
    }

    private function timezone(): DateTimeZone
    {
        return function_exists('wp_timezone')
            ? wp_timezone()
            : new DateTimeZone('UTC');
    }
}
