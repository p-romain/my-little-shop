<?php

namespace App\Service;

use Symfony\Component\Validator\ConstraintViolationListInterface;

final class ValidationErrorFormatter
{
    /**
     * @return array{error: string, violations: list<array{propertyPath: string, message: string}>}
     */
    public function format(ConstraintViolationListInterface $violations): array
    {
        $items = [];

        foreach ($violations as $violation) {
            $items[] = [
                'propertyPath' => $violation->getPropertyPath(),
                'message' => $violation->getMessage(),
            ];
        }

        return $this->formatItems($items);
    }

    /**
     * @param list<array{propertyPath: string, message: string}> $violations
     *
     * @return array{error: string, violations: list<array{propertyPath: string, message: string}>}
     */
    public function formatItems(array $violations): array
    {
        return [
            'error' => 'Validation failed.',
            'violations' => $violations,
        ];
    }
}
