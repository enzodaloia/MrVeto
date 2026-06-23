<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class DateFrExtension extends AbstractExtension
{
    private const MONTHS = [
        1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
        5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
        9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
    ];

    private const MONTHS_SHORT = [
        1 => 'janv.', 2 => 'févr.', 3 => 'mars', 4 => 'avr.',
        5 => 'mai', 6 => 'juin', 7 => 'juil.', 8 => 'août',
        9 => 'sept.', 10 => 'oct.', 11 => 'nov.', 12 => 'déc.',
    ];

    private const DAYS = [
        1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi',
        5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche',
    ];

    public function getFilters(): array
    {
        return [new TwigFilter('date_fr', [$this, 'dateFr'])];
    }

    public function dateFr(\DateTimeInterface|string $date, string $format): string
    {
        if (is_string($date)) {
            $date = new \DateTime($date);
        }

        $m = (int) $date->format('n');
        $d = (int) $date->format('N');

        return match ($format) {
            'short_month' => self::MONTHS_SHORT[$m],
            'day_name'    => self::DAYS[$d],
            'long_date'   => $date->format('j') . ' ' . self::MONTHS[$m] . ' ' . $date->format('Y'),
            'month_year'  => self::MONTHS[$m] . ' ' . $date->format('Y'),
            default       => $date->format($format),
        };
    }
}
