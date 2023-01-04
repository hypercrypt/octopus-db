<?php

require_once __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;

readonly class Dispatch {
    public DateTimeImmutable $start;
    public DateTimeImmutable $end;

    public function __construct(
        DateTime|DateTimeImmutable|string|int $start,
        DateTime|DateTimeImmutable|string|int $end,
        bool $expandCoreHours=true
    )
    {
        if (is_a($start, 'DateTime')) $this->start = DateTimeImmutable::createFromMutable($start);
        elseif (is_a($start, 'DateTimeImmutable')) $this->start = $start;
        elseif (is_string($start)) $this->start = new DateTimeImmutable($start);
        else $this->start = (new DateTimeImmutable())->setTimestamp($start);

        if (is_a($end, 'DateTime')) $this->end = DateTimeImmutable::createFromMutable($end);
        elseif (is_a($end, 'DateTimeImmutable')) $this->end = $end;
        elseif (is_string($end)) $this->end = new DateTimeImmutable($end);
        else $this->end = (new DateTimeImmutable)->setTimestamp($end);

        if ($expandCoreHours) {
            $isStartInCore = self::isCoreHours($this->start);
            $isEndInCore   = self::isCoreHours($this->end);

            if ($isEndInCore) $this->end = $this->end->setTime(5,30);

            if ($isStartInCore) {
                $this->start = $this->start->setTime(23, 30);
                if ($this->end < $this->start) {
                    $this->start = $this->start->sub(new DateInterval('P1D'));
                }
            }
        }
    }

    static function isCoreHours(DateTimeInterface $dateTime): bool
    {
        $dateTime_U = $dateTime->getTimestamp();
        $adjusted = $dateTime_U + 30*60;
        $adjusted %= 24*60*60;
        $adjusted /= 60*60;
        echo $dateTime->format('c'),' ' , $adjusted, PHP_EOL;
        return ($adjusted < 6);
    }

    public function expandWithSlot(Slot $slot): bool
    {
        if ($this->end <= $slot->start)
        {
            $this->end = $slot->end;
            return true;
        }
        return false;
    }

    public function __toString(): string
    {
        return $this->start->format('c') . ' ~> ' . $this->end->format('c');
    }

}