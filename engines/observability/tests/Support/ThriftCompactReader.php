<?php

declare(strict_types=1);

namespace Tavaan\Engine\Observability\Tests\Support;

/**
 * ThriftCompactReader — رمزگشای حداقلی Thrift Compact (فقط تست).
 *
 * برای round-trip تست کردن JaegerUdpExporter::buildBatch.
 *
 * @internal
 */
final class ThriftCompactReader
{
    private int $pos = 0;

    private int $lastFieldId = 0;

    /** @var list<int> */
    private array $fieldStack = [];

    public function __construct(private readonly string $buf)
    {
    }

    public function eof(): bool
    {
        return $this->pos >= \strlen($this->buf);
    }

    public function readByte(): int
    {
        return \ord($this->buf[$this->pos++]);
    }

    public function readVarint(): int
    {
        $result = 0;
        $shift = 0;
        while (true) {
            $b = $this->readByte();
            $result |= ($b & 0x7F) << $shift;
            if (($b & 0x80) === 0) {
                return $result;
            }
            $shift += 7;
            if ($shift > 63) {
                throw new \RuntimeException('varint too long');
            }
        }
    }

    public function readZigzag(): int
    {
        $n = $this->readVarint();

        return ($n >> 1) ^ -($n & 1);
    }

    public function readBinary(): string
    {
        $len = $this->readVarint();
        $out = \substr($this->buf, $this->pos, $len);
        $this->pos += $len;

        return $out;
    }

    public function readDouble(): float
    {
        /** @var array{1: float} $u */
        $u = \unpack('e', \substr($this->buf, $this->pos, 8));
        $this->pos += 8;

        return (float) $u[1];
    }

    /** @return array{version: int, type: int, seq: int, name: string} */
    public function readMessageBegin(): array
    {
        $proto = $this->readByte();
        if ($proto !== 0x82) {
            throw new \RuntimeException('bad protocol id: ' . $proto);
        }
        $vt = $this->readByte();
        $version = $vt & 0x1F;
        $type = ($vt >> 5) & 0x07;
        $seq = $this->readVarint();
        $name = $this->readBinary();

        return ['version' => $version, 'type' => $type, 'seq' => $seq, 'name' => $name];
    }

    public function structBegin(): void
    {
        $this->fieldStack[] = $this->lastFieldId;
        $this->lastFieldId = 0;
    }

    public function structEnd(): void
    {
        $this->lastFieldId = \array_pop($this->fieldStack) ?? 0;
    }

    /**
     * خواندن هدر فیلد بعدی؛ در STOP (0x00) مقدار null.
     *
     * @return array{type: int, id: int}|null
     */
    public function readFieldBegin(): ?array
    {
        $b = $this->readByte();
        if ($b === 0x00) {
            return null;
        }
        $type = $b & 0x0F;
        $delta = ($b >> 4) & 0x0F;
        if ($delta !== 0) {
            $this->lastFieldId += $delta;
        } else {
            $this->lastFieldId = $this->readZigzag();
        }

        return ['type' => $type, 'id' => $this->lastFieldId];
    }

    /** @return array{elemType: int, size: int} */
    public function readListBegin(): array
    {
        $b = $this->readByte();
        $size = ($b >> 4) & 0x0F;
        $elemType = $b & 0x0F;
        if ($size === 15) {
            $size = $this->readVarint();
        }

        return ['elemType' => $elemType, 'size' => $size];
    }

    /** خواندن مقدار فیلد براساس نوع فشرده. */
    public function readValue(int $type): mixed
    {
        return match ($type) {
            1 => true,
            2 => false,
            3 => $this->readByte(),
            4, 5, 6 => $this->readZigzag(),
            7 => $this->readDouble(),
            8 => $this->readBinary(),
            default => throw new \RuntimeException('readValue: type ' . $type . ' needs manual handling'),
        };
    }

    /**
     * پیمایش یک struct و بازگرداندن مقادیر فیلدهای اسکالر.
     * struct/list به callback واگذار می‌شود یا skip.
     *
     * @return array<int, mixed> id → مقدار (برای struct/list: 'struct'|array{elemType,size})
     */
    public function readStructFields(): array
    {
        $this->structBegin();
        $out = [];
        while (($field = $this->readFieldBegin()) !== null) {
            $type = $field['type'];
            if ($type === 12) {
                $out[$field['id']] = 'struct';
                $this->skipStruct();
            } elseif ($type === 9) {
                $list = $this->readListBegin();
                $out[$field['id']] = $list;
                $this->skipList($list['elemType'], $list['size']);
            } else {
                $out[$field['id']] = $this->readValue($type);
            }
        }
        $this->structEnd();

        return $out;
    }

    public function skipStruct(): void
    {
        $this->structBegin();
        while (($field = $this->readFieldBegin()) !== null) {
            $this->skipValue($field['type']);
        }
        $this->structEnd();
    }

    public function skipValue(int $type): void
    {
        if ($type === 12) {
            $this->skipStruct();

            return;
        }
        if ($type === 9 || $type === 10) {
            $list = $this->readListBegin();
            $this->skipList($list['elemType'], $list['size']);

            return;
        }
        $this->readValue($type);
    }

    public function skipList(int $elemType, int $size): void
    {
        for ($i = 0; $i < $size; $i++) {
            $this->skipValue($elemType);
        }
    }

    /** ورود دستی به فیلد struct (بدون skip) — برای پیمایش هدفمند. */
    public function enterStruct(): void
    {
        $this->structBegin();
    }

    public function leaveStruct(): void
    {
        // باید تا STOP پیش برویم
        while (($field = $this->readFieldBegin()) !== null) {
            $this->skipValue($field['type']);
        }
        $this->structEnd();
    }

    /** جست‌وجوی فیلد با id مشخص در struct جاری و ورود به آن (struct). */
    public function seekStructField(int $id): bool
    {
        while (($field = $this->readFieldBegin()) !== null) {
            if ($field['id'] === $id && $field['type'] === 12) {
                $this->structBegin();

                return true;
            }
            $this->skipValue($field['type']);
        }

        return false;
    }

    /** جست‌وجوی فیلد list در struct جاری. */
    public function seekListField(int $id): ?array
    {
        while (($field = $this->readFieldBegin()) !== null) {
            if ($field['id'] === $id && $field['type'] === 9) {
                return $this->readListBegin();
            }
            $this->skipValue($field['type']);
        }

        return null;
    }

    /** جست‌وجوی فیلد اسکالر در struct جاری. */
    public function seekScalarField(int $id): mixed
    {
        while (($field = $this->readFieldBegin()) !== null) {
            if ($field['id'] === $id && $field['type'] !== 12 && $field['type'] !== 9) {
                return $this->readValue($field['type']);
            }
            $this->skipValue($field['type']);
        }

        return null;
    }
}
