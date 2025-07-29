<?php

namespace Simp\Core\modules\structures\content_types\field;

use Symfony\Component\HttpFoundation\Request;

interface FieldBuilderInterface
{
    public function build(Request $request, string $field_type, array $options = []): string;
    public function fieldArray(Request $request, string $field_type, string $entity_type): array;
    public function extensionInfo(string $type): array;
    public function getFieldHandler(): string;

}
