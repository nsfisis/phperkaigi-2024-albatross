<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Models;

enum ExecutionStatus: string
{
    case Pending = 'Pending';
    case Running = 'Running';
    case IE = 'IE';
    case RE = 'RE';
    case WA = 'WA';
    case TLE = 'TLE';
    case AC = 'AC';

    public function label(): string
    {
        return match ($this) {
            self::Pending => '実行待機中',
            self::Running => '実行中',
            self::IE => '内部エラー',
            self::RE => '実行時エラー',
            self::WA => '不正解',
            self::TLE => '時間制限超過',
            self::AC => 'OK',
        };
    }

    public function showLoadingIndicator(): bool
    {
        return match ($this) {
            self::Pending => true,
            self::Running => true,
            self::IE => false,
            self::RE => false,
            self::WA => false,
            self::TLE => false,
            self::AC => false,
        };
    }

    public function toInt(): int
    {
        return match ($this) {
            self::Pending => 0,
            self::Running => 1,
            self::IE => 2,
            self::RE => 3,
            self::WA => 4,
            self::TLE => 5,
            self::AC => 6,
        };
    }

    public static function fromInt(int $n): self
    {
        // @phpstan-ignore-next-line
        return match ($n) {
            0 => self::Pending,
            1 => self::Running,
            2 => self::IE,
            3 => self::RE,
            4 => self::WA,
            5 => self::TLE,
            6 => self::AC,
        };
    }

    public static function fromString(string $s): self
    {
        // @phpstan-ignore-next-line
        return match ($s) {
            'Pending' => self::Pending,
            'Running' => self::Running,
            'IE' => self::IE,
            'RE' => self::RE,
            'WA' => self::WA,
            'TLE' => self::TLE,
            'AC' => self::AC,
        };
    }
}
