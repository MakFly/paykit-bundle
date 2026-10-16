<?php

declare(strict_types=1);

namespace PayKit\Bundle\Tests\Functional;

use PayKit\Bundle\Security\RequiresFeature;
use Symfony\Component\HttpFoundation\Response;

final class GatedController
{
    #[RequiresFeature('pro_models')]
    public function pro(): Response
    {
        return new Response('pro content');
    }

    #[RequiresFeature('messages', consume: 1)]
    public function chat(): Response
    {
        return new Response('answer');
    }
}
