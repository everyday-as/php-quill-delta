<?php

namespace Everyday\QuillDelta;

use JetBrains\PhpStorm\ArrayShape;

class DeltaOp implements \JsonSerializable
{
    private array $attributes = [];

    public function __construct(private array|string $insert, array $attributes = [])
    {
        foreach ($attributes as $attribute => $value) {
            if (!empty($value)) {
                $this->setAttribute($attribute, $value);
            }
        }
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function hasAttribute(string $attribute): bool
    {
        return isset($this->attributes[$attribute]);
    }

    public function getAttribute(string $attribute): mixed
    {
        return $this->attributes[$attribute] ?? null;
    }

    public function setAttribute(string $attribute, mixed $value): void
    {
        $this->attributes[$attribute] = $value;
    }

    public function removeAttributes(string ...$attributes): void
    {
        foreach ($attributes as $attribute) {
            unset($this->attributes[$attribute]);
        }
    }

    public function getInsert(): array|string
    {
        return $this->insert;
    }

    public function setInsert(array|string $insert): void
    {
        $this->insert = $insert;
    }

    public function isBlockModifier(): bool
    {
        return "\n" === $this->insert && !empty($this->attributes);
    }

    public function isEmbed(): bool
    {
        return is_array($this->insert);
    }

    public function isNoOp(): bool
    {
        return empty($this->insert) && empty($this->attributes);
    }

    /**
     * Compact the op.
     */
    public function compact()
    {
        $to_remove = [];

        if ($this->hasAttribute('indent') && 0 === $this->getAttribute('indent')) {
            $to_remove[] = 'indent';
        }

        $this->removeAttributes(...$to_remove);
    }

    #[ArrayShape(['insert' => 'array|string', 'attributes' => 'array'])]
    public function toArray(): array
    {
        $op = [
            'insert' => $this->insert,
        ];

        if (!empty($this->attributes)) {
            $op['attributes'] = $this->attributes;
        }

        return $op;
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public static function text(string $text, array $attributes = []): self
    {
        return new self($text, $attributes);
    }

    public static function embed(string $type, array|string $data, array $attributes = []): self
    {
        return new self([
            $type => $data,
        ], $attributes);
    }

    public static function blockModifier(string $type, $value = true): self
    {
        return self::text("\n", [$type => $value]);
    }

    /**
     * Apply an array of attributes to an array of DeltaOps.
     */
    public static function applyAttributes(array $ops, array $attributes): void
    {
        foreach ($ops as $op) {
            foreach ($attributes as $attribute => $value) {
                if (!empty($value)) {
                    $op->setAttribute($attribute, $value);
                }
            }
        }
    }
}
