<?php

declare(strict_types=1);

namespace Tavaan\Engine\Observability\Tracing\Exporters;

/**
 * تَوان | Tavaan — Observability
 * ThriftCompactWriter — رمزگذار پروتکل Thrift Compact (خودنوشته).
 *
 * برای JaegerUdpExporter (jaeger.thrift over UDP). انواع فشرده:
 * BOOLEAN_TRUE=1, BOOLEAN_FALSE=2, BYTE=3, I16=4, I32=5, I64=6,
 * DOUBLE=7, BINARY=8, LIST=9, SET=10, MAP=11, STRUCT=12.
 *
 * @internal
 *
 * @api 1.0
 * @see docs/03-engines/observability.md
 */
final class ThriftCompactWriter
{
    public const TYPE_BOOL_TRUE = 1;
    public const TYPE_BOOL_FALSE = 2;
    public const TYPE_BYTE = 3;
    public const TYPE_I16 = 4;
    public const TYPE_I32 = 5;
    public const TYPE_I64 = 6;
    public const TYPE_DOUBLE = 7;
    public const TYPE_BINARY = 8;
    public const TYPE_LIST = 9;
    public const TYPE_STRUCT = 12;

    private string $buf = '';

    /** @var list<int> پشتهٔ lastFieldId برای structهای تودرتو */
    private array $fieldStack = [];

    private int $lastFieldId = 0;

    /** سرآیند پیام RPC فشرده: protocol-id, version|type, seqid, name. */
    public function messageBegin(string $name, int $type = 1, int $seqId = 0): void
    {
        $this->buf .= "\x82";                              // protocol id
        $this->buf .= \chr((1 & 0x1F) | (($type & 0x07) << 5));   // version 1 | type
        $this->writeVarint($seqId);                          // seqid (varint ساده)
        $this->writeBinary($name);
    }

    public function structBegin(): void
    {
        $this->fieldStack[] = $this->lastFieldId;
        $this->lastFieldId = 0;
    }

    public function structEnd(): void
    {
        $this->buf .= "\x00";   // STOP
        $this->lastFieldId = \array_pop($this->fieldStack) ?? 0;
    }

    public function fieldBegin(int $type, int $id): void
    {
        $delta = $id - $this->lastFieldId;
        if ($delta > 0 && $delta <= 15) {
            $this->buf .= \chr((($delta & 0x0F) << 4) | $type);
        } else {
            $this->buf .= \chr($type);
            $this->writeZigzag($id);   // i16
        }
        $this->lastFieldId = $id;
    }

    public function writeBoolField(int $id, bool $value): void
    {
        $this->fieldBegin($value ? self::TYPE_BOOL_TRUE : self::TYPE_BOOL_FALSE, $id);
    }

    public function writeI32Field(int $id, int $value): void
    {
        $this->fieldBegin(self::TYPE_I32, $id);
        $this->writeZigzag($value);
    }

    public function writeI64Field(int $id, int $value): void
    {
        $this->fieldBegin(self::TYPE_I64, $id);
        $this->writeZigzag($value);
    }

    public function writeDoubleField(int $id, float $value): void
    {
        $this->fieldBegin(self::TYPE_DOUBLE, $id);
        $this->buf .= \pack('e', $value);
    }

    public function writeStringField(int $id, string $value): void
    {
        $this->fieldBegin(self::TYPE_BINARY, $id);
        $this->writeBinary($value);
    }

    /** آغاز فیلد struct — فراخوان structBegin/structEnd را به عهدهٔ شما می‌گذارد. */
    public function writeStructFieldBegin(int $id): void
    {
        $this->fieldBegin(self::TYPE_STRUCT, $id);
    }

    /** آغاز فیلد list شامل header. */
    public function writeListFieldBegin(int $id, int $elemType, int $size): void
    {
        $this->fieldBegin(self::TYPE_LIST, $id);
        $this->writeListHeader($elemType, $size);
    }

    public function writeListHeader(int $elemType, int $size): void
    {
        if ($size < 15) {
            $this->buf .= \chr((($size & 0x0F) << 4) | $elemType);
        } else {
            $this->buf .= \chr(0xF0 | $elemType);
            $this->writeVarint($size);
        }
    }

    public function writeBinary(string $value): void
    {
        $this->writeVarint(\strlen($value));
        $this->buf .= $value;
    }

    /** varint بدون علامت (منطق ۶۴ بیتی — شیفت منطقی با mask). */
    public function writeVarint(int $n): void
    {
        while (true) {
            if (($n & ~0x7F) === 0) {
                $this->buf .= \chr($n & 0x7F);

                return;
            }
            $this->buf .= \chr(($n & 0x7F) | 0x80);
            $n = ($n >> 7) & (\PHP_INT_MAX >> 6);   // شیفت منطقی ۷ بیت
        }
    }

    public function writeZigzag(int $n): void
    {
        $this->writeVarint(($n << 1) ^ ($n >> 63));
    }

    public function buffer(): string
    {
        return $this->buf;
    }

    public function length(): int
    {
        return \strlen($this->buf);
    }

    public function reset(): void
    {
        $this->buf = '';
        $this->fieldStack = [];
        $this->lastFieldId = 0;
    }
}
