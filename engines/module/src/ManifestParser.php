<?php

declare(strict_types=1);

namespace Tavaan\Engine\Module;

use Tavaan\Engine\Module\Exceptions\ModuleException;

/**
 * تَوان | Tavaan — Module
 * ManifestParser — پارس + Schema validation فایل module.json (DOC-038 §4).
 *
 * @api 1.0
 * @see docs/03-engines/module.md
 */
final class ManifestParser
{
    /** @var list<string> فیلدهای الزامی */
    private const REQUIRED_FIELDS = ['id', 'name', 'version', 'autoload', 'providers'];

    public function parse(string $path): Manifest
    {
        $raw = \file_get_contents($path);
        if ($raw === false) {
            throw ModuleException::manifestUnreadable($path);
        }

        try {
            /** @var array<string, mixed> $data */
            $data = \json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw ModuleException::manifestInvalidJson($path, $e->getMessage());
        }

        $this->validateSchema($data, $path);

        return Manifest::fromArray($data, \dirname($path));
    }

    /** @param array<string, mixed> $data */
    private function validateSchema(array $data, string $path): void
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!isset($data[$field])) {
                throw ModuleException::manifestMissingField($path, $field);
            }
        }

        if (\preg_match('/^[a-z0-9\-]+\.[a-z0-9\-\.]+$/', (string) $data['id']) !== 1) {
            throw ModuleException::invalidId((string) $data['id']);
        }

        if (!$this->isSemver((string) $data['version'])) {
            throw ModuleException::invalidVersion((string) $data['version']);
        }
    }

    private function isSemver(string $version): bool
    {
        return \preg_match('/^\d+\.\d+\.\d+(-[0-9A-Za-z\-.]+)?(\+[0-9A-Za-z\-.]+)?$/', $version) === 1;
    }
}
