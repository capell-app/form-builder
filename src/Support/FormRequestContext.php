<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Support;

use Capell\FormBuilder\Data\FormRequestContextData;
use Capell\FormBuilder\Livewire\FormComponent;
use Capell\FormBuilder\Livewire\FormElementComponent;
use Livewire\Component;
use Livewire\Mechanisms\HandleComponents\ComponentContext;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use Throwable;

final class FormRequestContext
{
    /** @param array<string, mixed> $snapshot */
    public static function captureVerifiedSnapshot(array $snapshot): void
    {
        $memo = $snapshot['memo'] ?? null;
        $origin = is_array($memo) ? ($memo['origin'] ?? null) : null;
        $path = is_array($memo) ? ($memo['path'] ?? null) : null;
        $basePath = is_array($memo) ? ($memo['basePath'] ?? null) : null;

        // This listener runs only after checksum verification, for each
        // component separately. Never read components.0 or unsigned headers.
        request()->attributes->set(self::class, is_string($origin) && $origin !== '' && is_string($path) && $path !== '' && is_string($basePath)
            ? new FormRequestContextData($origin, $path, $basePath)
            : null);
    }

    public static function dehydrate(Component $component, ComponentContext $context): void
    {
        if (! $component instanceof FormElementComponent && ! $component instanceof FormComponent) {
            return;
        }

        // Public routing information, covered by Livewire's checksum. Children
        // mounted during an update inherit the verified parent's issuing origin.
        $requestContext = self::current();
        $context->addMemo('origin', $requestContext->origin ?? '');
        // Livewire's memo.path excludes the application's public base path,
        // including a trusted proxy prefix. Preserve it inside the checksum too.
        $context->addMemo('basePath', $requestContext->basePath ?? '');
    }

    public static function current(): ?FormRequestContextData
    {
        $request = request();

        if ($request->attributes->has(self::class)) {
            $context = $request->attributes->get(self::class);

            return $context instanceof FormRequestContextData ? $context : null;
        }

        if (app(HandleRequests::class)->isLivewireRequest() || app(HandleRequests::class)->isLivewireRoute()) {
            return null;
        }

        try {
            return new FormRequestContextData($request->getSchemeAndHttpHost(), $request->path(), $request->getBaseUrl());
        } catch (Throwable) {
            return null;
        }
    }

    public static function url(): ?string
    {
        $context = self::current();

        return $context instanceof FormRequestContextData
            ? request()->getSchemeAndHttpHost() . $context->basePath . '/' . ltrim($context->path, '/')
            : null;
    }
}
