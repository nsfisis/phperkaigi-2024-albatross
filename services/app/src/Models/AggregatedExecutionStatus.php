<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Models;

enum AggregatedExecutionStatus: string
{
    case UpdateNeeded = 'UpdateNeeded';
    case Pending = 'Pending';
    case Failed = 'Failed';
    case OK = 'OK';

    public function label(): string
    {
        return match ($this) {
            self::UpdateNeeded => '実行待機中',
            self::Pending => '実行待機中',
            self::Failed => '失敗',
            self::OK => 'OK',
        };
    }

    public function showLoadingIndicator(): bool
    {
        return match ($this) {
            self::UpdateNeeded => true,
            self::Pending => true,
            self::Failed => false,
            self::OK => false,
        };
    }

    public function toInt(): int
    {
        return match ($this) {
            self::UpdateNeeded => 0,
            self::Pending => 1,
            self::Failed => 2,
            self::OK => 3,
        };
    }

    public static function fromInt(int $n): self
    {
        // @phpstan-ignore-next-line
        return match ($n) {
            0 => self::UpdateNeeded,
            1 => self::Pending,
            2 => self::Failed,
            3 => self::OK,
        };
    }
}
