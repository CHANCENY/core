<?php

namespace Simp\Core\extends\wiki\src\enum;

enum WikiStatusEnum: string
{
    case DRAFT = 'draft';
    case ARCHIVED = 'archived';
    case PUBLISHED = 'published';
}
