<?php
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Integration;

use Itools\SmartArray\Tests\Support\SmartArrayTestCase;

/**
 * Enforces the U+200B rendering convention across docs/*.md, so authors adding
 * examples don't have to remember it - a miss fails here with the file and line.
 *
 * The convention: HTML entities in example output inside code fences carry a
 * zero-width space (U+200B) after the "&" so PHPStorm's Markdown preview shows
 * the entity instead of decoding it, and every file containing a U+200B carries
 * the one-line header comment explaining it (and only those files, so the
 * comment never describes something the file doesn't do).
 *
 * ai-reference.md is the exception by design: AI assistants read it as raw
 * bytes, so it must stay byte-exact with no U+200B anywhere (its own header
 * says so).
 */
class DocsConventionsTest extends SmartArrayTestCase
{
    private const ZWSP = "\u{200B}";

    /** @return array<string, string> filename => contents for every docs/*.md page */
    private function docPages(): array
    {
        $pages = [];
        foreach (glob(__DIR__ . '/../../docs/*.md') as $path) {
            $pages[basename($path)] = file_get_contents($path);
        }
        $this->assertNotEmpty($pages, 'no docs/*.md files found - wrong path?');
        return $pages;
    }

    public function testAiReferenceStaysByteExact(): void
    {
        $pages = $this->docPages();
        $this->assertStringNotContainsString(
            self::ZWSP,
            $pages['ai-reference.md'],
            'ai-reference.md must contain no U+200B: AI assistants copy it as raw bytes',
        );
    }

    public function testFenceEntitiesCarryTheZwsp(): void
    {
        $violations = [];
        foreach ($this->docPages() as $file => $contents) {
            if ($file === 'ai-reference.md') {
                continue;
            }
            $inFence = false;
            foreach (explode("\n", $contents) as $lineNumber => $line) {
                if (preg_match('/^\s*```/', $line)) {
                    $inFence = !$inFence;
                    continue;
                }
                // A bare entity only matches when no ZWSP follows the "&"
                if ($inFence && preg_match('/&[A-Za-z]+;/', $line, $match)) {
                    $violations[] = sprintf('%s:%d - %s needs a U+200B after "&"', $file, $lineNumber + 1, $match[0]);
                }
            }
        }
        $this->assertSame([], $violations, "Entities in code fences need a zero-width space after \"&\":\n" . implode("\n", $violations));
    }

    public function testHeaderCommentMatchesZwspUsage(): void
    {
        $violations = [];
        foreach ($this->docPages() as $file => $contents) {
            if ($file === 'ai-reference.md') {
                continue;
            }
            $hasZwsp    = str_contains($contents, self::ZWSP);
            $hasComment = str_contains(strtok($contents, "\n"), 'U+200B');
            if ($hasZwsp && !$hasComment) {
                $violations[] = "$file contains U+200B but is missing the header comment explaining it";
            }
            if (!$hasZwsp && $hasComment) {
                $violations[] = "$file carries the U+200B header comment but contains none - drop the comment";
            }
        }
        $this->assertSame([], $violations, implode("\n", $violations));
    }
}
