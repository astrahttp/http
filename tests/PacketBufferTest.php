<?php

declare(strict_types=1);

namespace Astra\Http\Tests;

use Astra\Http\Exception\AstraException;
use Astra\Http\PacketBuffer;
use PHPUnit\Framework\TestCase;

final class PacketBufferTest extends TestCase
{
    public function testReadU8(): void
    {
        $buffer = new PacketBuffer("\x05");
        self::assertSame(5, $buffer->readU8());
        self::assertSame(1, $buffer->offset);
    }

    public function testReadU16BigEndian(): void
    {
        $buffer = new PacketBuffer("\x01\x02");
        self::assertSame(258, $buffer->readU16());
    }

    public function testReadU32BigEndian(): void
    {
        $buffer = new PacketBuffer("\x00\x00\x01\x00");
        self::assertSame(256, $buffer->readU32());
    }

    public function testReadStringUsesU16Length(): void
    {
        $buffer = new PacketBuffer("\x00\x03abc");
        self::assertSame('abc', $buffer->readString());
        self::assertSame(5, $buffer->offset);
    }

    public function testReadStringZeroLengthReturnsEmpty(): void
    {
        $buffer = new PacketBuffer("\x00\x00");
        self::assertSame('', $buffer->readString());
    }

    public function testReadBytesUsesU32Length(): void
    {
        $buffer = new PacketBuffer("\x00\x00\x00\x02hi");
        self::assertSame('hi', $buffer->readBytes());
        self::assertSame(6, $buffer->offset);
    }

    public function testSequentialReads(): void
    {
        $buffer = new PacketBuffer("\x07\x00\x02ok");
        self::assertSame(7, $buffer->readU8());
        self::assertSame('ok', $buffer->readString());
    }

    public function testUnderflowThrows(): void
    {
        $this->expectException(AstraException::class);
        (new PacketBuffer(''))->readU8();
    }

    public function testStringUnderflowThrows(): void
    {
        $this->expectException(AstraException::class);
        // Declares length 10 but provides 3 bytes.
        (new PacketBuffer("\x00\x0Aabc"))->readString();
    }
}
