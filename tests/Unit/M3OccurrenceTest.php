<?php
namespace UOP\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use UOP\Domain\Events\OccurrenceWindow;

final class M3OccurrenceTest extends TestCase {
    public function test_unambiguous_and_autumn_dst_fold_are_persisted_as_utc(): void {
        $summer=new OccurrenceWindow('2026-07-20T19:00:00+02:00','2026-07-20T21:00:00+02:00','Europe/Berlin');
        self::assertSame('2026-07-20 17:00:00',$summer->start_utc());
        self::assertSame('2026-07-20 19:00:00',$summer->end_utc());
        $first=new OccurrenceWindow('2026-10-25T02:30:00+02:00','2026-10-25T03:00:00+01:00','Europe/Berlin');
        $second=new OccurrenceWindow('2026-10-25T02:30:00+01:00','2026-10-25T03:00:00+01:00','Europe/Berlin');
        self::assertSame('2026-10-25 00:30:00',$first->start_utc());
        self::assertSame('2026-10-25 01:30:00',$second->start_utc());
    }

    public function test_rejects_gap_wrong_offset_noniana_and_reversed_windows(): void {
        foreach ([
            ['2027-03-28T02:30:00+01:00','2027-03-28T04:00:00+02:00','Europe/Berlin'],
            ['2027-03-28T02:30:00+02:00','2027-03-28T04:00:00+02:00','Europe/Berlin'],
            ['2026-07-20T19:00:00+01:00','2026-07-20T21:00:00+02:00','Europe/Berlin'],
            ['2026-07-20T19:00:00+02:00','2026-07-20T19:00:00+02:00','Europe/Berlin'],
            ['2026-07-20T19:00:00+02:00','2026-07-20T21:00:00+02:00','Europe/NotReal'],
            ['2026-07-20T19:00:00','2026-07-20T21:00:00','Europe/Berlin'],
        ] as [$start,$end,$zone]) {
            try { new OccurrenceWindow($start,$end,$zone); self::fail('Invalid interval accepted'); }
            catch (InvalidArgumentException) { self::assertTrue(true); }
        }
    }
}
