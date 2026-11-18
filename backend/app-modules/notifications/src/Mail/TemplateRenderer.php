<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Mail;

final class TemplateRenderer
{
    private const string PLACEHOLDER = '/\{([a-z_]+)\}/';

    /**
     * @return list<string>
     */
    public static function placeholders(string $text): array
    {
        preg_match_all(self::PLACEHOLDER, $text, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * @param  array<string, string>  $variables
     */
    public static function plain(string $text, array $variables): string
    {
        return (string) preg_replace_callback(
            self::PLACEHOLDER,
            fn (array $match): string => $variables[$match[1]] ?? $match[0],
            $text,
        );
    }

    /**
     * @param  array<string, string>  $variables
     */
    public static function singleLine(string $text, array $variables): string
    {
        return mb_trim((string) preg_replace('/\s+/', ' ', self::plain($text, $variables)));
    }

    /**
     * @param  array<string, string>  $variables
     * @return list<string>
     */
    public static function htmlParagraphs(string $text, array $variables): array
    {
        $escapedVariables = array_map(fn (string $value): string => e($value), $variables);
        $paragraphs = preg_split('/\R\s*\R/', mb_trim($text)) ?: [];

        return array_values(array_map(
            fn (string $paragraph): string => nl2br(self::plain(e(mb_trim($paragraph)), $escapedVariables), false),
            array_filter($paragraphs, fn (string $paragraph): bool => mb_trim($paragraph) !== ''),
        ));
    }
}
