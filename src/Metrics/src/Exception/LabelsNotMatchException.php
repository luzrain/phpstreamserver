<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Metrics\Exception;

final class LabelsNotMatchException extends \InvalidArgumentException
{
    public function __construct(array $labels, array $givenLabels)
    {
        if ($labels === [] && $givenLabels !== []) {
            $text = \sprintf('Labels do not match. Should not contain labels, %s assigned', \json_encode($givenLabels));
        } else if($labels !== [] && $givenLabels === []) {
            $text = \sprintf('Labels do not match. Should contain %s labels, no labels assigned', \json_encode($labels));
        } else {
            $text = \sprintf('Labels do not match. Should contain %s labels, %s assigned', \json_encode($labels), \json_encode($givenLabels));
        }

        parent::__construct($text);
    }
}
