<?php

declare(strict_types=1);

namespace PHPeste\Branding\Domains;

use PHPeste\Branding\Contracts\DnsResolver;

class SystemDnsResolver implements DnsResolver
{
    public function txt(string $host): array
    {
        return $this->lookup($host, DNS_TXT, 'txt');
    }

    public function cname(string $host): array
    {
        return array_map(fn (string $target): string => mb_rtrim(mb_strtolower($target), '.'), $this->lookup($host, DNS_CNAME, 'target'));
    }

    /**
     * @return list<string>
     */
    private function lookup(string $host, int $type, string $field): array
    {
        $records = @dns_get_record($host, $type);

        if (!is_array($records)) {
            return [];
        }

        $values = [];

        foreach ($records as $record) {
            $value = $record[$field] ?? null;

            if (is_string($value)) {
                $values[] = $value;
            }
        }

        return $values;
    }
}
