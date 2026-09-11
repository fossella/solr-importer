<?php

declare(strict_types=1);

namespace SolrImport\Transformers;

final class TransformerRegistry
{
    /** @var array<string,TransformerInterface> */
    private array $transformers = [];

    public function __construct()
    {
        $this->register('regex', new RegexTransformer());
        $this->register('date', new DateFormatTransformer());
        $this->register('template', new TemplateTransformer());
        $this->register('script', new ScriptTransformer());
    }

    public function register(string $name, TransformerInterface $transformer): void
    {
        $this->transformers[$name] = $transformer;
    }

    public function get(string $name): TransformerInterface
    {
        if (!isset($this->transformers[$name])) {
            throw new \InvalidArgumentException("Unknown transformer: {$name}");
        }

        return $this->transformers[$name];
    }
}
