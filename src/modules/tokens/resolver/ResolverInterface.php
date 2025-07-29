<?php

namespace Simp\Core\modules\tokens\resolver;

interface ResolverInterface
{
    /**
     * Resolves the given type by processing the provided tokens and options.
     *
     * @param string $type The type to be resolved.
     * @param array $tokens An array of tokens used in the resolution process.
     * @param array $options An array of additional options for resolution.
     * @return array The resolved output based on the type, tokens, and options.
     */
    public function resolver(string $type, array $tokens, array $options): array;
}