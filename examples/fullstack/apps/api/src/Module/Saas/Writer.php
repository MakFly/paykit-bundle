<?php

declare(strict_types=1);

namespace App\Module\Saas;

/**
 * Stand-in for a language model: a deterministic rewrite, so the demo works
 * offline. Only the billing around it is real.
 */
final class Writer
{
    private const FILLERS = ['en fait', 'vraiment', 'juste', 'un peu', 'du coup', 'carrément', 'globalement'];

    public function rewrite(string $prompt, string $model): string
    {
        $text = preg_replace('/\s+/u', ' ', trim($prompt)) ?? '';
        $removed = 0;
        foreach (self::FILLERS as $filler) {
            // A filler takes its trailing comma with it: "En fait, je" gives "je".
            $text = preg_replace('/\b'.preg_quote($filler, '/').'\b,?\s*/iu', '', $text, -1, $count) ?? $text;
            $removed += $count;
        }
        $sentences = array_values(array_filter(array_map(
            static function (string $sentence): string {
                $sentence = trim(preg_replace('/\s+([,.!?])/u', '$1', $sentence) ?? $sentence, " \t,;");

                return '' === $sentence ? '' : mb_strtoupper(mb_substr($sentence, 0, 1)).mb_substr($sentence, 1);
            },
            preg_split('/(?<=[.!?])\s+/u', $text) ?: [],
        )));
        if ([] === $sentences) {
            return 'Écris quelques phrases et je te propose une version plus claire.';
        }
        $sentences = array_map(static fn (string $s): string => preg_match('/[.!?…]$/u', $s) ? $s : $s.'.', $sentences);

        if ('expert' !== $model) {
            return "Version plus claire :\n\n".implode(' ', $sentences);
        }

        // The opening sentence usually carries the topic; a short text needs no summary.
        $summary = count($sentences) > 2 ? "\n\nEn une phrase :\n\n".$sentences[0] : '';

        return "Version retravaillée :\n\n".implode("\n\n", $sentences).$summary
            ."\n\n".match ($removed) {
                0 => 'Aucun tic de langage à retirer : j’ai seulement découpé le texte en paragraphes courts.',
                1 => 'J’ai retiré 1 tic de langage et découpé le texte en paragraphes courts.',
                default => "J’ai retiré {$removed} tics de langage et découpé le texte en paragraphes courts.",
            };
    }
}
