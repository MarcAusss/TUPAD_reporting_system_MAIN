<?php

namespace App\Services\Auth;

class TemporaryPasswordGenerator
{
    private const UPPERCASE = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    private const LOWERCASE = 'abcdefghijkmnopqrstuvwxyz';
    private const NUMBERS = '23456789';
    private const SYMBOLS = '!@#$%*-_+=';

    public function generate(int $length = 20): string
    {
        $length = max(16, $length);

        $characters = [
            $this->randomCharacter(self::UPPERCASE),
            $this->randomCharacter(self::LOWERCASE),
            $this->randomCharacter(self::NUMBERS),
            $this->randomCharacter(self::SYMBOLS),
        ];

        $alphabet = self::UPPERCASE.self::LOWERCASE.self::NUMBERS.self::SYMBOLS;

        while (count($characters) < $length) {
            $characters[] = $this->randomCharacter($alphabet);
        }

        for ($index = count($characters) - 1; $index > 0; $index--) {
            $swapIndex = random_int(0, $index);
            [$characters[$index], $characters[$swapIndex]] = [
                $characters[$swapIndex],
                $characters[$index],
            ];
        }

        return implode('', $characters);
    }

    private function randomCharacter(string $characters): string
    {
        return $characters[random_int(0, strlen($characters) - 1)];
    }
}
