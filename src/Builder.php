<?php

namespace Everyday\QuillDelta;

use JetBrains\PhpStorm\Pure;

class Builder
{
    private array $ops = [];

    public function push(DeltaOp $op): self
    {
        $this->ops[] = $op;

        return $this;
    }

    public function text(string $text, array $attributes = []): self
    {
        return $this->push(DeltaOp::text($text, $attributes));
    }

    public function embed(string $type, array|string $data, array $attributes = []): self
    {
        return $this->push(DeltaOp::embed($type, $data, $attributes));
    }

    public function line(array $attributes = []): self
    {
        return $this->push(DeltaOp::text(Delta::LINE_SEPARATOR, $attributes));
    }

    #[Pure]
    public function build(): Delta
    {
        $last = end($this->ops);
        if (!$last || $last->getInsert() !== Delta::LINE_SEPARATOR) {
            $this->line();
        }

        return new Delta($this->ops);
    }
}
