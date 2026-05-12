<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\ValidationExceptionSubscriber;
use App\Service\ValidationErrorFormatter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class ValidationExceptionSubscriberTest extends TestCase
{
    public function testSubscribesToKernelExceptionEvent(): void
    {
        self::assertSame([
            KernelEvents::EXCEPTION => 'onKernelException',
        ], ValidationExceptionSubscriber::getSubscribedEvents());
    }

    public function testFormatsValidationFailedExceptionAsJsonResponse(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Name is required.', null, [], null, 'name', null),
            new ConstraintViolation('Latitude is required.', null, [], null, 'latitude', null),
        ]);
        $exception = new BadRequestHttpException(
            'Validation failed.',
            new ValidationFailedException(null, $violations),
        );
        $event = $this->event($exception);

        $this->subscriber()->onKernelException($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(400, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('content-type'));
        self::assertSame([
            'error' => 'Validation failed.',
            'violations' => [
                ['propertyPath' => 'name', 'message' => 'Name is required.'],
                ['propertyPath' => 'latitude', 'message' => 'Latitude is required.'],
            ],
        ], json_decode((string) $response->getContent(), true));
    }

    public function testUsesHttpExceptionStatusCode(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Invalid.', null, [], null, 'field', null),
        ]);
        $exception = new \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException(
            'Validation failed.',
            new ValidationFailedException(null, $violations),
        );
        $event = $this->event($exception);

        $this->subscriber()->onKernelException($event);

        self::assertSame(422, $event->getResponse()?->getStatusCode());
    }

    public function testIgnoresExceptionsWithoutValidationFailure(): void
    {
        $event = $this->event(new \RuntimeException('Unexpected failure.'));

        $this->subscriber()->onKernelException($event);

        self::assertNull($event->getResponse());
    }

    private function subscriber(): ValidationExceptionSubscriber
    {
        return new ValidationExceptionSubscriber(new ValidationErrorFormatter());
    }

    private function event(\Throwable $exception): ExceptionEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);

        return new ExceptionEvent($kernel, Request::create('/api/shops'), HttpKernelInterface::MAIN_REQUEST, $exception);
    }
}
