<?php

namespace PHPStan\Rules\Doctrine\ORMAttributes\EmbeddableProperties;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class EmbeddableClass
{
	#[ORM\Column(type: 'string')]
	private string $foo;

	#[ORM\Column(type: 'string', nullable: true)]
	private string|null $bar = null;

	public function __construct(string $foo)
	{
        $this->foo = $foo;
	}
}

#[ORM\Entity]
class EntityWithEmbeddableProperties
{
	#[ORM\Id]
    #[ORM\GeneratedValue]
	#[ORM\Column]
	private int $id;

	#[ORM\Embedded(class: EmbeddableClass::class)]
	private EmbeddableClass $embeddableClass;

	public function __construct(EmbeddableClass $embeddableClass)
	{
		$this->embeddableClass = $embeddableClass;
	}

	public function getId(): int
	{
		return $this->id;
	}

	public function getEmbeddableClass(): EmbeddableClass
	{
		return $this->embeddableClass;
	}
}
