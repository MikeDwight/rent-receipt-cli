<?php

declare(strict_types=1);

namespace RentReceiptCli\Infrastructure\Seed;

use Symfony\Component\Yaml\Yaml;

final class YamlSeedLoader
{
    public function load(string $path): array
    {
        if (!is_file($path)) {
            throw new \RuntimeException(sprintf("Seed file not found: %s", $path));
        }

        $data = Yaml::parseFile($path);

        if (!is_array($data)) {
            throw new \RuntimeException("Invalid seed file: root must be a YAML mapping.");
        }

        // Root
        $this->requireKey($data, "version");

        // Owner (matches owners table)
        $this->requireKey($data, "owner");
        $this->requireKey($data, "owner.full_name");
        $this->requireKey($data, "owner.email");
        $this->requireKey($data, "owner.address");
        

        // Property (matches properties table)
        $this->requireKey($data, "property");
        $this->requireKey($data, "property.label");
        $this->requireKey($data, "property.address");
        $this->requireKey($data, "property.rent_amount_cents");
        $this->requireKey($data, "property.charges_amount_cents");

        return $data;
    }

    private function requireKey(array $data, string $path): void
    {
        $value = $this->getValue($data, $path);

        if ($value === null || $value === "") {
            throw new \RuntimeException(sprintf("Missing required key \"%s\"", $path));
        }
    }

    private function getValue(array $data, string $path): mixed
    {
        $parts = explode(".", $path);
        $current = $data;

        foreach ($parts as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return null;
            }
            $current = $current[$part];
        }

        return $current;
    }
}
