<?php
declare(strict_types=1);

namespace IntegerNet\CallbackProxy;

/**
 * @method \ArrayIterator getInnerIterator()
 */
class Targets extends \IteratorIterator
{

    public function __construct(Target ...$targets)
    {
        parent::__construct(new \ArrayIterator($targets));
    }

    public function current(): Target
    {
        return parent::current();
    }

    public static function fromConfig(array $config): self
    {
        return new self(
            ...array_map(
                [Target::class, 'fromConfig'],
                $config
            )
        );
    }
}
