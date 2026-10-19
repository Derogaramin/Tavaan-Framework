<?php

declare(strict_types=1);

namespace Tavaan\Engine\Admin\Support;

/**
 * تَوان | Tavaan — Admin
 * ChartRenderer — نمودار SVG درون‌خطی (بدون JS — صفر‌وابستگی، DOC-078 §5).
 *
 * @api 1.0
 * @see docs/03-engines/admin.md
 */
final class ChartRenderer
{
    private const WIDTH = 560;

    private const HEIGHT = 180;

    private const PAD = 28;

    /**
     * @param array<array-key, mixed> $data برچسب → مقدار عددی
     */
    public static function render(array $data, string $type = 'line'): string
    {
        $values = [];
        $labels = [];
        foreach ($data as $label => $value) {
            if (!\is_numeric($value)) {
                continue;
            }
            $labels[] = (string) $label;
            $values[] = (float) $value;
        }

        if ($values === []) {
            return '<div style="color:#94a3b8;padding:24px;text-align:center">داده‌ای برای نمودار وجود ندارد</div>';
        }

        $max = \max($values);
        $min = \min($values, 0.0);
        $span = $max - $min;
        $span = $span <= 0 ? 1.0 : $span;

        $innerWidth = self::WIDTH - 2 * self::PAD;
        $innerHeight = self::HEIGHT - 2 * self::PAD;
        $step = \count($values) > 1 ? $innerWidth / (\count($values) - 1) : 0.0;

        $points = [];
        foreach ($values as $index => $value) {
            $x = self::PAD + ($step > 0 ? $index * $step : $innerWidth / 2);
            $y = self::PAD + $innerHeight - (($value - $min) / $span) * $innerHeight;
            $points[] = [\round($x, 1), \round($y, 1)];
        }

        $body = $type === 'bar'
            ? self::bars($points, $values, $innerHeight)
            : self::line($points);

        $labelsSvg = self::labels($labels, $points);

        return '<svg viewBox="0 0 ' . self::WIDTH . ' ' . self::HEIGHT . '" role="img" '
            . 'style="width:100%;height:auto;font-family:inherit">' . $body . $labelsSvg . '</svg>';
    }

    /**
     * @param list<array{0: float, 1: float}> $points
     * @param list<float>                     $values
     */
    private static function line(array $points): string
    {
        $path = [];
        foreach ($points as $index => [$x, $y]) {
            $path[] = ($index === 0 ? 'M' : 'L') . $x . ' ' . $y;
        }
        $dots = '';
        foreach ($points as [$x, $y]) {
            $dots .= '<circle cx="' . $x . '" cy="' . $y . '" r="3" fill="#f59e0b"/>';
        }

        return '<path d="' . \implode(' ', $path) . '" fill="none" stroke="#f59e0b" stroke-width="2"/>' . $dots;
    }

    /**
     * @param list<array{0: float, 1: float}> $points
     * @param list<float>                     $values
     */
    private static function bars(array $points, array $values, float $innerHeight): string
    {
        unset($values);
        $count = \count($points);
        $slot = $count > 0 ? (self::WIDTH - 2 * self::PAD) / $count : 0.0;
        $barWidth = \max(4.0, $slot * 0.6);
        $bars = '';
        foreach ($points as $index => [$x, $y]) {
            $height = \max(1.0, self::PAD + $innerHeight - $y);
            $bars .= '<rect x="' . \round($x - $barWidth / 2, 1) . '" y="' . $y
                . '" width="' . \round($barWidth, 1) . '" height="' . \round($height, 1)
                . '" rx="2" fill="#f59e0b" opacity="0.85"/>';
            unset($index);
        }

        return $bars;
    }

    /**
     * @param list<string>                    $labels
     * @param list<array{0: float, 1: float}> $points
     */
    private static function labels(array $labels, array $points): string
    {
        $svg = '';
        $count = \count($points);
        foreach ($labels as $index => $label) {
            if ($count > 8 && $index % (int) \ceil($count / 8) !== 0) {
                continue;   // جلوگیری از شلوغی
            }
            if (!isset($points[$index])) {
                continue;
            }
            [$x, ] = $points[$index];
            $escaped = \htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $svg .= '<text x="' . $x . '" y="' . (self::HEIGHT - 8) . '" font-size="10" fill="#64748b" text-anchor="middle">' . $escaped . '</text>';
        }

        return $svg;
    }
}
