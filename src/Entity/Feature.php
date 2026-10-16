<?php

declare(strict_types=1);

namespace PayKit\Bundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use PayKit\Bundle\Catalog\FeatureType;

#[ORM\Entity]
#[ORM\Table(name: 'paykit_feature')]
class Feature
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', length: 64)]
    private string $id;

    #[ORM\Column(name: 'type', length: 16, enumType: FeatureType::class)]
    private FeatureType $type;

    public function __construct(string $id, FeatureType $type)
    {
        $this->id = $id;
        $this->type = $type;
    }

    public function getId(): string { return $this->id; }
    public function getType(): FeatureType { return $this->type; }
    public function setType(FeatureType $type): void { $this->type = $type; }
}
