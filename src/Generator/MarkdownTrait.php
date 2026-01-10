<?php

declare(strict_types=1);

namespace ConfluenceApiDoc\Generator;

use DH\Adf\Node\Block\Paragraph;
use DH\Adf\Node\Inline\Text;
use DH\Adf\Node\Mark\Code;
use DH\Adf\Node\Node;

trait MarkdownTrait
{
    /**
     * @param string $text
     *
     * @return Node[]
     */
    protected function createFromMarkdown(string $text): array
    {
        $mode = 'text';
        $buffer = '';
        $i = 0;
        $length = strlen($text);

        $paragraph = (new Paragraph());

        while ($i < $length) {
            $currentChar = $text[$i];

            if ($currentChar === '`') {
                if ($mode === 'text') {
                    // Finish saving a text part and start saving a code part
                    $this->appendIfNotEmpty($paragraph, $buffer);
                    $buffer = '';
                    $mode = 'code';
                } else {
                    // Finish saving a code part and return to a text part
                    $this->appendIfNotEmpty($paragraph, $buffer, new Code());
                    $buffer = '';
                    $mode = 'text';
                }
            } else {
                // Add a character to the current buffer depending on the current mode
                $buffer .= $currentChar;
            }

            $i++;
        }

        // Last text or code part
        if ($mode === 'text') {
            $this->appendIfNotEmpty($paragraph, $buffer);
        } else {
            $this->appendIfNotEmpty($paragraph, $buffer, new Code());
        }

        return [$paragraph];
    }

    private function appendIfNotEmpty(Node $parent, string $text, ...$markers): void
    {
        if ($text) {
            $parent->append(new Text($text, ...$markers));
        }
    }
}
