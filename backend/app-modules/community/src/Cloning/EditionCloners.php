<?php

declare(strict_types=1);

namespace PHPeste\Community\Cloning;

use PHPeste\Community\Contracts\EditionCloner;

class EditionCloners
{
    /**
     * @return array<string, EditionCloner>
     */
    public function all(): array
    {
        $cloners = [];

        foreach (app()->tagged(EditionCloner::TAG) as $cloner) {
            if ($cloner instanceof EditionCloner) {
                $cloners[$cloner->key()] = $cloner;
            }
        }

        return $cloners;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }
}
