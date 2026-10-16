<?php

declare(strict_types=1);

namespace App\Module;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class ModuleController
{
    /** @param iterable<DemoModule> $modules */
    public function __construct(#[AutowireIterator(DemoModule::TAG)] private readonly iterable $modules)
    {
    }

    #[Route('/api/modules', methods: ['GET'], format: 'json')]
    public function list(): JsonResponse
    {
        $modules = [];
        foreach ($this->modules as $module) {
            $modules[] = ['id' => $module->id(), 'basePath' => '/api/'.$module->id(), ...$module->describe()];
        }

        return new JsonResponse(['modules' => $modules]);
    }
}
