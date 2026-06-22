<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class PhoneFormatExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('format_phone', [$this, 'formatPhone']),
        ];
    }

    public function formatPhone(?string $phoneNumber): string
    {
        if (!$phoneNumber) {
            return '';
        }

        $tel = str_replace(' ', '', $phoneNumber);

        if (str_starts_with($tel, '0') && strlen($tel) === 10) {
            return '+33 ' . substr($tel, 1, 1) . ' ' . substr($tel, 2, 2) . ' ' . substr($tel, 4, 2) . ' ' . substr($tel, 6, 2) . ' ' . substr($tel, 8, 2);
        }

        if (str_starts_with($tel, '+33') && strlen($tel) === 12) {
            return '+33 ' . substr($tel, 3, 1) . ' ' . substr($tel, 4, 2) . ' ' . substr($tel, 6, 2) . ' ' . substr($tel, 8, 2) . ' ' . substr($tel, 10, 2);
        }
        return $phoneNumber;
    }
}
