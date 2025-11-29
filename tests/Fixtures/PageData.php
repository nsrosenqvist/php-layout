<?php

declare(strict_types=1);

namespace PhpLayout\Tests\Fixtures;

/**
 * Example typed page data model for testing.
 */
final class PageData
{
    public function __construct(
        public readonly string $title,
        public readonly string $author,
        public readonly \DateTimeImmutable $publishedAt,
    ) {
    }
}
