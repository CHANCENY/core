<?php

namespace Simp\Core\extends\wiki\src\Entity;

class WikiContent implements \Stringable
{
    private string $content;

    public function __construct(string $content)
    {
        $this->setContent($content);
    }

    public function __toString(): string
    {
        return $this->content;
    }

    /**
     * Get the raw HTML content.
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Set the content, removing unsafe tags while keeping safe HTML intact.
     */
    public function setContent(string $content): void
    {
        // Remove <form> tags
        $content = preg_replace('#<form[^>]*>(.*?)</form>#is', '', $content);

        // Remove <script> tags
        $content = preg_replace('#<script[^>]*>(.*?)</script>#is', '', (string) $content);

        // Remove <iframe> tags
        $content = preg_replace('#<iframe[^>]*>(.*?)</iframe>#is', '', (string) $content);

        // Remove <object> tags
        $content = preg_replace('#<object[^>]*>(.*?)</object>#is', '', (string) $content);

        $this->content = $content;
    }

    /**
     * Get a plain-text summary of the content.
     */
    public function getSummary(int $length = 150): string
    {
        return mb_strimwidth(strip_tags($this->content), 0, $length, "...");
    }

    /**
     * Get the word count of the content.
     */
    public function getWordCount(): int
    {
        return str_word_count(strip_tags($this->content));
    }
}
