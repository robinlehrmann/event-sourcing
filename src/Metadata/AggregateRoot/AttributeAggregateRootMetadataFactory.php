<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Normalize;
use Patchlevel\EventSourcing\Attribute\SerializedName;
use Patchlevel\EventSourcing\Attribute\Snapshot;
use Patchlevel\EventSourcing\Attribute\SuppressMissingApply;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;

use function array_key_exists;
use function array_map;
use function array_merge;
use function class_exists;

final class AttributeAggregateRootMetadataFactory implements AggregateRootMetadataFactory
{
    /** @var array<class-string<AggregateRoot>, AggregateRootMetadata> */
    private array $aggregateMetadata = [];

    /**
     * @param class-string<AggregateRoot> $aggregate
     */
    public function metadata(string $aggregate): AggregateRootMetadata
    {
        if (array_key_exists($aggregate, $this->aggregateMetadata)) {
            return $this->aggregateMetadata[$aggregate];
        }

        $reflector = new ReflectionClass($aggregate);

        $aggregateName = $this->findAggregateName($reflector);
        [$suppressEvents, $suppressAll] = $this->findSuppressMissingApply($reflector);
        $applyMethods = $this->findApplyMethods($reflector, $aggregate);
        [$snapshotStore, $snapshotBatch] = $this->findSnapshot($reflector);
        $properties = $this->getPropertyMetadataList($reflector);

        $metadata = new AggregateRootMetadata(
            $aggregateName,
            $applyMethods,
            $properties,
            $suppressEvents,
            $suppressAll,
            $snapshotStore,
            $snapshotBatch,
        );

        $this->aggregateMetadata[$aggregate] = $metadata;

        return $metadata;
    }

    /**
     * @return array{array<class-string, true>, bool}
     */
    private function findSuppressMissingApply(ReflectionClass $reflector): array
    {
        $suppressEvents = [];
        $suppressAll = false;

        $attributes = $reflector->getAttributes(SuppressMissingApply::class);

        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();

            if ($instance->suppressAll()) {
                $suppressAll = true;

                continue;
            }

            foreach ($instance->suppressEvents() as $event) {
                $suppressEvents[$event] = true;
            }
        }

        return [$suppressEvents, $suppressAll];
    }

    private function findAggregateName(ReflectionClass $reflector): string
    {
        $attributeReflectionList = $reflector->getAttributes(Aggregate::class);

        if (!$attributeReflectionList) {
            throw new ClassIsNotAnAggregate($reflector->getName());
        }

        $aggregateAttribute = $attributeReflectionList[0]->newInstance();

        return $aggregateAttribute->name();
    }

    /**
     * @return array{?string, ?int}
     */
    private function findSnapshot(ReflectionClass $reflector): array
    {
        $attributeReflectionList = $reflector->getAttributes(Snapshot::class);

        if (!$attributeReflectionList) {
            return [null, null];
        }

        $attribute = $attributeReflectionList[0]->newInstance();

        return [$attribute->name(), $attribute->batch()];
    }

    /**
     * @param class-string<AggregateRoot> $aggregate
     *
     * @return array<class-string, string>
     */
    private function findApplyMethods(ReflectionClass $reflector, string $aggregate): array
    {
        $applyMethods = [];

        $methods = $reflector->getMethods();

        foreach ($methods as $method) {
            $attributes = $method->getAttributes(Apply::class);

            if ($attributes === []) {
                continue;
            }

            $methodName = $method->getName();
            $eventClasses = [];
            $hasOneEmptyApply = false;
            $hasOneNonEmptyApply = false;

            foreach ($attributes as $attribute) {
                $applyAttribute = $attribute->newInstance();
                $eventClass = $applyAttribute->eventClass();

                if ($eventClass !== null) {
                    $hasOneNonEmptyApply = true;
                    $eventClasses[] = $eventClass;

                    continue;
                }

                if ($hasOneEmptyApply) {
                    throw new DuplicateEmptyApplyAttribute($methodName);
                }

                $hasOneEmptyApply = true;
                $eventClasses = array_merge($eventClasses, $this->getEventClassesByPropertyTypes($method));
            }

            if ($hasOneEmptyApply && $hasOneNonEmptyApply) {
                throw new MixedApplyAttributeUsage($methodName);
            }

            foreach ($eventClasses as $eventClass) {
                if (array_key_exists($eventClass, $applyMethods)) {
                    throw new DuplicateApplyMethod(
                        $aggregate,
                        $eventClass,
                        $applyMethods[$eventClass],
                        $methodName
                    );
                }

                $applyMethods[$eventClass] = $methodName;
            }
        }

        return $applyMethods;
    }

    /**
     * @return array<class-string>
     */
    private function getEventClassesByPropertyTypes(ReflectionMethod $method): array
    {
        $propertyType = $method->getParameters()[0]->getType();
        $methodName = $method->getName();
        $eventClasses = [];

        if ($propertyType === null) {
            throw new ArgumentTypeIsMissing($methodName);
        }

        /* needs psalm to undestand ReflectionIntersectionType
        if ($propertyType instanceof ReflectionIntersectionType) {
            throw new RuntimeException();
        }
        */

        if ($propertyType instanceof ReflectionNamedType) {
            $eventClasses = [$propertyType->getName()];
        }

        if ($propertyType instanceof ReflectionUnionType) {
            $eventClasses = array_map(
                static fn (ReflectionNamedType $reflectionType) => $reflectionType->getName(),
                $propertyType->getTypes()
            );
        }

        $result = [];

        foreach ($eventClasses as $eventClass) {
            if (!class_exists($eventClass)) {
                throw new ArgumentTypeIsNotAClass($methodName, $eventClass);
            }

            $result[] = $eventClass;
        }

        return $result;
    }

    /**
     * @return array<string, AggregateRootPropertyMetadata>
     */
    private function getPropertyMetadataList(ReflectionClass $reflectionClass): array
    {
        $properties = [];

        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            $reflectionProperty->setAccessible(true);
            $fieldName = $reflectionProperty->getName();

            $attributeReflectionList = $reflectionProperty->getAttributes(SerializedName::class);

            if ($attributeReflectionList !== []) {
                $attribute = $attributeReflectionList[0]->newInstance();
                $fieldName = $attribute->name();
            }

            $attributeReflectionList = $reflectionProperty->getAttributes(Normalize::class);

            $normalizer = null;

            if ($attributeReflectionList !== []) {
                $attribute = $attributeReflectionList[0]->newInstance();
                $normalizer = $attribute->normalizer();
            }

            $properties[$reflectionProperty->getName()] = new AggregateRootPropertyMetadata(
                $fieldName,
                $reflectionProperty,
                $normalizer
            );
        }

        return $properties;
    }
}
