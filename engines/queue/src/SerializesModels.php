<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue;

/**
 * تَوان | Tavaan — Queue
 * SerializesModels — مدل‌های ORM فقط با «کلاس + کلید» سریال می‌شوند (DOC-061 §7).
 *
 * 🔒 کل آبجکت مدل هرگز در payload ذخیره نمی‌شود؛ هنگام بازسازی
 * مدل با `find($key)` تازه از دیتابیس خوانده می‌شود.
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
trait SerializesModels
{
    /**
     * props عمومی (Serializer این را بر __serialize ترجیح می‌دهد).
     *
     * 🔒 جای‌گذاری مدل‌ها در `Serializer::prepare` انجام می‌شود
     * (بازگشتی — شامل آرایه‌ها و props تودرتو).
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        $props = [];
        foreach ((new \ReflectionObject($this))->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            if ($prop->isStatic() || !$prop->isInitialized($this)) {
                continue;
            }
            $props[$prop->getName()] = $prop->getValue($this);
        }

        // promoted private/protected readonly props نیز با نام ثبت می‌شوند
        foreach ((new \ReflectionObject($this))->getProperties(\ReflectionProperty::IS_PRIVATE | \ReflectionProperty::IS_PROTECTED) as $prop) {
            if ($prop->isStatic() || !$prop->isInitialized($this)) {
                continue;
            }
            $prop->setAccessible(true);
            $value = $prop->getValue($this);
            // فقط مدل‌های ORM به‌شکل ارجاع ذخیره می‌شوند؛ بقیه privateها رد می‌شوند
            if ($this->isQueueableModel($value)) {
                $props[$prop->getName()] = $value;
            }
        }

        return $props;
    }

    private function isQueueableModel(mixed $value): bool
    {
        return \class_exists(\Tavaan\Engine\Database\ORM\Model::class)
            && $value instanceof \Tavaan\Engine\Database\ORM\Model;
    }
}
