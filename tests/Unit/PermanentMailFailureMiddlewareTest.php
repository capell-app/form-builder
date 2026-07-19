<?php

declare(strict_types=1);

use Capell\FormBuilder\Mail\FormSubmissionAutoresponderMail;
use Capell\FormBuilder\Queue\Middleware\SuppressInactivePostmarkRecipientFailure;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Contracts\HttpClient\ResponseInterface;

function formBuilderPostmarkFailure(int $errorCode): HttpTransportException
{
    $response = Mockery::mock(ResponseInterface::class);
    $response->shouldReceive('getContent')->with(false)->andReturn(json_encode(['ErrorCode' => $errorCode], JSON_THROW_ON_ERROR));

    return new HttpTransportException('redacted', $response);
}

it('suppresses only Postmark inactive recipient failures', function (): void {
    $middleware = new SuppressInactivePostmarkRecipientFailure;
    $job = new stdClass;
    $inactive = formBuilderPostmarkFailure(406);

    expect(fn () => $middleware->handle($job, fn () => throw $inactive))->not->toThrow(Throwable::class)
        ->and(fn () => $middleware->handle($job, fn () => throw new RuntimeException('retry')))->toThrow(RuntimeException::class, 'retry');
});

it('attaches permanent failure middleware to queued form mail', function (): void {
    $mail = new FormSubmissionAutoresponderMail('Subject', 'Body');

    expect($mail->middleware)->toHaveCount(1)
        ->and($mail->middleware[0])->toBeInstanceOf(SuppressInactivePostmarkRecipientFailure::class)
        ->and($mail->afterCommit)->toBeTrue();
});
