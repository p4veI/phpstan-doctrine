<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\Common\Annotations\AnnotationException;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Embeddable;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\Rules\Properties\ReadWritePropertiesExtension;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use Throwable;
use function class_exists;
use function get_class;
use function in_array;

class PropertiesExtension implements ReadWritePropertiesExtension
{
	private ObjectMetadataResolver $objectMetadataResolver;

	private AnnotationReader|null $annotationReader;

	public function __construct(ObjectMetadataResolver $objectMetadataResolver)
	{
		$this->objectMetadataResolver = $objectMetadataResolver;
		$this->annotationReader = class_exists(AnnotationReader::class) ? new AnnotationReader() : null;
	}

	public function isAlwaysRead(PropertyReflection $property, string $propertyName): bool
	{
		$declaringClass = $property->getDeclaringClass();
		$className = $declaringClass->getName();
		$metadata = $this->objectMetadataResolver->getClassMetadata($className);
		if ($metadata === null) {
            return $this->isEmbeddableClass($declaringClass);
		}

		return $metadata->hasField($propertyName) || $metadata->hasAssociation($propertyName);
	}

	public function isAlwaysWritten(PropertyReflection $property, string $propertyName): bool
	{
		$declaringClass = $property->getDeclaringClass();
		$className = $declaringClass->getName();
		$metadata = $this->objectMetadataResolver->getClassMetadata($className);
		if ($metadata === null) {
            return $this->isEmbeddableClass($declaringClass);
		}

		if (!$metadata->hasField($propertyName) && !$metadata->hasAssociation($propertyName)) {
			return false;
		}

		if (isset($metadata->fieldMappings[$propertyName])) {
			$mapping = $metadata->fieldMappings[$propertyName];
			if (isset($mapping['generated']) && $mapping['generated'] !== ClassMetadata::GENERATED_NEVER) {
				return true;
			}
		}

		if ($metadata->isReadOnly && !$declaringClass->hasConstructor()) {
			return true;
		}

		if ($metadata->versionField === $propertyName) {
			return true;
		}

		return $this->isGeneratedIdentifier($metadata, $propertyName);
	}

	public function isInitialized(PropertyReflection $property, string $propertyName): bool
	{
		$declaringClass = $property->getDeclaringClass();
		$className = $declaringClass->getName();
		$metadata = $this->objectMetadataResolver->getClassMetadata($className);
		if ($metadata === null) {
			return false;
		}

		if (!$metadata->hasField($propertyName) && !$metadata->hasAssociation($propertyName)) {
			return false;
		}

		if ($this->isGeneratedIdentifier($metadata, $propertyName)) {
			return true;
		}

		return $metadata->isReadOnly && !$declaringClass->hasConstructor();
	}

	/**
	 * @param ClassMetadata<object> $metadata
	 */
	private function isGeneratedIdentifier(ClassMetadata $metadata, string $propertyName): bool
	{
		if ($metadata->isIdentifierNatural()) {
			return false;
		}

		try {
			return in_array($propertyName, $metadata->getIdentifierFieldNames(), true);
		} catch (Throwable $e) {
			$mappingException = 'Doctrine\ORM\Mapping\MappingException';
			if (!$e instanceof $mappingException) {
				throw $e;
			}

			return false;
		}
	}

	private function isEmbeddableClass(ClassReflection $classReflection): bool
	{
		$nativeReflection = $classReflection->getNativeReflection();

		$attributes = $nativeReflection->getAttributes();
		foreach ($attributes as $attribute) {
			if ($attribute->getName() === Embeddable::class) {
				return true;
			}
		}

		if ($this->annotationReader === null) {
			return false;
		}

		try {
			$annotations = $this->annotationReader->getClassAnnotations($nativeReflection);
		} catch (AnnotationException $e) {
			return false;
		}

		foreach ($annotations as $annotation) {
			if (get_class($annotation) === Embeddable::class) {
				return true;
			}
		}

		return false;
	}

}
