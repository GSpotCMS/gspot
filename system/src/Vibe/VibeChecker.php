<?php

declare(strict_types=1);

namespace GSpot\Vibe;

/**
 * Heuristic "Vibe Assurance" scoring for pages and HTML (no external AI required).
 */
final class VibeChecker
{
    /** @var list<string> */
    private const CORPORATE_PHRASES = [
        'synergy',
        'leverage',
        'stakeholder',
        'paradigm shift',
        'best-in-class',
        'thought leader',
        'circle back',
        'low-hanging fruit',
        'move the needle',
        'human resources',
        'employee handbook',
        'compliance training',
    ];

    /** @var list<string> */
    private const RAVE_SIGNALS = [
        'neon',
        'rave',
        'synth',
        'bpm',
        'drop',
        'groundswell',
        'midnight',
        'grunge',
        'hacker',
        'ship it',
    ];

    /**
     * @param array{vibe?: string, bpm?: int|string|null, energy?: string} $aura
     * @return array<string, mixed>
     */
    public function analyze(string $markdownOrHtml, array $aura = [], ?string $siteDescription = null): array
    {
        $text = strtolower(strip_tags($markdownOrHtml));
        $raw = $markdownOrHtml;
        $score = 100;
        $flags = [];

        foreach (self::CORPORATE_PHRASES as $phrase) {
            if (str_contains($text, $phrase)) {
                $score -= 12;
                $flags[] = $this->flag('hr_manual', 'warn', "Corporate phrase detected: \"{$phrase}\".");
            }
        }

        if (preg_match('/font-family:\s*[^;]*(?:arial|helvetica|times new roman)/i', $raw)) {
            $score -= 15;
            $flags[] = $this->flag('safe_fonts', 'warn', 'Inline styles reference Arial/Helvetica/Times (HR-manual energy).');
        }

        if (preg_match('/lorem ipsum/i', $raw)) {
            $score -= 25;
            $flags[] = $this->flag('placeholder', 'critical', 'Placeholder lorem ipsum still in content.');
        }

        $wordCount = str_word_count($text);
        if ($wordCount > 0 && $wordCount < 40) {
            $score -= 8;
            $flags[] = $this->flag('thin_content', 'info', 'Very short copy; hard to judge brand resonance.');
        }

        if (!preg_match('/<h1|^#\s/m', $raw) && !preg_match('/<h1/i', $raw)) {
            $score -= 5;
            $flags[] = $this->flag('missing_hero', 'info', 'No H1 detected; landing pages usually need a clear headline.');
        }

        $raveHits = 0;
        foreach (self::RAVE_SIGNALS as $signal) {
            if (str_contains($text, $signal)) {
                $raveHits++;
            }
        }

        $vibe = strtolower(trim((string) ($aura['vibe'] ?? '')));
        $energy = strtolower(trim((string) ($aura['energy'] ?? '')));
        $bpm = $this->normalizeBpm($aura['bpm'] ?? null);

        if ($vibe !== '' && $raveHits === 0 && !str_contains($text, $vibe)) {
            $score -= 10;
            $flags[] = $this->flag('aura_mismatch', 'warn', "Frontmatter vibe \"{$vibe}\" is not reflected in body copy.");
        }

        if ($bpm !== null && $bpm >= 120 && $raveHits === 0 && count(array_filter($flags, fn ($f) => ($f['id'] ?? '') === 'hr_manual')) > 0) {
            $score -= 10;
            $flags[] = $this->flag('bpm_soul_clash', 'warn', 'High BPM aura with corporate copy is a vibe collision.');
        }

        if ($energy === 'high' && $wordCount > 800 && substr_count($text, '.') > 40) {
            $score -= 6;
            $flags[] = $this->flag('energy_density', 'info', 'High energy aura but long, dense paragraphs; consider breaking the beat.');
        }

        if (str_contains($raw, '[mud-pattern') || str_contains($raw, 'mud-pattern')) {
            $score = min(100, $score + 5);
        }

        if ($siteDescription !== null && $siteDescription !== '') {
            $desc = strtolower($siteDescription);
            if (str_contains($desc, 'find the spot') && !str_contains($text, 'ship')) {
                $flags[] = $this->flag('brand_echo', 'info', 'Site tagline is strong; echo "Ship it" in CTAs when it fits.');
            }
        }

        $score = max(0, min(100, $score));
        $critical = array_filter($flags, fn ($f) => ($f['severity'] ?? '') === 'critical');

        return [
            'score' => $score,
            'grade' => $this->grade($score),
            'pass' => $score >= 60 && $critical === [],
            'flags' => array_values($flags),
            'hints' => $this->hints($score, $flags, $vibe, $energy),
            'aura' => [
                'vibe' => $vibe !== '' ? $vibe : null,
                'bpm' => $bpm,
                'energy' => $energy !== '' ? $energy : null,
            ],
        ];
    }

    /**
     * @param list<array<string, string>> $flags
     * @return list<string>
     */
    private function hints(int $score, array $flags, string $vibe, string $energy): array
    {
        $hints = [];
        if ($score < 70) {
            $hints[] = 'Swap corporate nouns for concrete visuals (neon, bass, deploy receipts).';
        }
        if ($vibe === '') {
            $hints[] = 'Add frontmatter aura: vibe, bpm, energy (see /docs/vibe-check).';
        }
        if ($energy === 'high') {
            $hints[] = 'Use shorter paragraphs and one accent color punch per section.';
        }
        foreach ($flags as $flag) {
            if (($flag['id'] ?? '') === 'hr_manual') {
                $hints[] = 'Run gspot_apply_vibe with synthwave or your brand preset, then re-check.';
                break;
            }
        }
        if (count($hints) < 3 && $score >= 80) {
            $hints[] = 'Vibe lands; run gspot_assembly_recipe before ship for MCP checklist parity.';
        }

        return array_slice(array_values(array_unique($hints)), 0, 3);
    }

    private function grade(int $score): string
    {
        return match (true) {
            $score >= 90 => 'transcendent',
            $score >= 75 => 'landing',
            $score >= 60 => 'needs_polish',
            default => 'soul_emergency',
        };
    }

    private function normalizeBpm(mixed $bpm): ?int
    {
        if ($bpm === null || $bpm === '') {
            return null;
        }
        $n = (int) $bpm;

        return $n > 0 ? min(999, $n) : null;
    }

    /**
     * @return array{id: string, severity: string, message: string}
     */
    private function flag(string $id, string $severity, string $message): array
    {
        return ['id' => $id, 'severity' => $severity, 'message' => $message];
    }
}
