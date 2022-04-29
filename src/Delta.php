<?php

namespace Everyday\QuillDelta;

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;

class Delta implements \JsonSerializable
{
    /**
     * @var DeltaOp[]
     */
    private array $ops;

    public const LINE_SEPARATOR = "\n";

    /**
     * @param DeltaOp[] $ops
     */
    public function __construct(array $ops)
    {
        $this->ops = $ops;
    }

    /**
     * @return DeltaOp[]
     */
    public function getOps(): array
    {
        return $this->ops;
    }

    /**
     * Compact the delta, returning the number of passes.
     */
    public function compact(): int
    {
        $passes = 0;

        while ($this->doCompactionPass()) {
            $passes++;
        }

        foreach ($this->ops as $op) {
            $op->compact();
        }

        $this->addMissingNewLine();

        return $passes;
    }

    /**
     * Perform a single compaction pass.
     */
    public function doCompactionPass(): bool
    {
        $i = -1;
        $start_count = count($this->ops);

        while (isset($this->ops[++$i]) && isset($this->ops[$i + 1])) {
            $op1 = $this->ops[$i];

            if ($op1->isNoOp()) {
                $this->ops[$i] = false;

                continue;
            }

            if ($op1->isEmbed()) {
                continue;
            }

            $op1_insert = $op1->getInsert();

            $op2 = $this->ops[$i + 1];

            if ($op2->isEmbed()) {
                $i++;

                continue;
            }

            $op2_insert = $op2->getInsert();

            if (!isset($this->ops[$i + 2]) && $op2_insert === "\n") {
                // Nothing more to optimize.
                break;
            }

            $op1_attributes = $op1->getAttributes();

            if ($op1_attributes !== $op2->getAttributes()) {
                continue;
            }

            $this->ops[$i] = DeltaOp::text($op1_insert.$op2_insert, $op1_attributes);

            $this->ops[$i + 1] = false;

            $i++;
        }

        $this->ops = array_values(array_filter($this->ops));

        return $start_count > count($this->ops);
    }

    /**
     * Convert a delta to plaintext.
     */
    public function toPlainText(): string
    {
        $string = '';

        foreach ($this->ops as $op) {
            if (is_string($insert = $op->getInsert())) {
                $string .= $insert;
            }
        }

        return $string;
    }

    #[ArrayShape(['ops' => 'mixed'])]
    public function toArray(): array
    {
        $delta = clone $this;

        // A valid delta must be compact
        $delta->compact();

        return ['ops'               => array_map(
            static fn (DeltaOp $op) => $op->toArray(),
            $delta->ops
        )];
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    #[Pure]
    public static function build(): Builder
    {
        return new Builder();
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    protected function addMissingNewLine(): void
    {
        // A valid delta must **ALWAYS** end in a new line
        $last = end($this->ops);

        if ($last === false) {
            return;
        }

        $insert = $last->getInsert();

        if ($insert === self::LINE_SEPARATOR) {
            return;
        }

        if (is_string($insert) && str_ends_with($insert, self::LINE_SEPARATOR)) {
            $last->setInsert(substr($insert, 0, -1));
        }

        $this->ops[] = DeltaOp::text(self::LINE_SEPARATOR);
    }
}
