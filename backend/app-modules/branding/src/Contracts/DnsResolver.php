<?php

declare(strict_types=1);

namespace PHPeste\Branding\Contracts;

interface DnsResolver
{
    /**
     * @return list<string>
     */
    public function txt(string $host): array;

    /**
     * @return list<string>
     */
    public function cname(string $host): array;
}
