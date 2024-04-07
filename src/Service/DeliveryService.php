<?php

namespace WebnetzSurcharge\Service;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;

/**
 * gives the delivery information
 */
class DeliveryService
{
    protected EntityRepository $deliveryTimeRepository;

    /**
     * @param EntityRepository $deliveryTimeRepository
     */
    public function __construct(EntityRepository $deliveryTimeRepository)
    {
        $this->deliveryTimeRepository = $deliveryTimeRepository;
    }

    /**
     * Checks if a product's delivery time is instant.
     *
     * @param ProductEntity $productEntity The product entity to check.
     * @param Context $context The context for the operation.
     * @return bool True if the delivery time is instant, false otherwise.
     */
    public function isDeliveryTimeInstant(ProductEntity $productEntity, Context $context): bool
    {
        // Create criteria to search for delivery time by ID

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $productEntity->getDeliveryTimeId()));

        // Search for delivery time entity
        /** @var DeliveryTimeEntity $deliveryTime */
        $deliveryTime = $this->deliveryTimeRepository->search($criteria, $context)->first();

        // Check if delivery time exists and 'max' is 0 - it means instant
        return $deliveryTime !== null && !$deliveryTime->getMax();
    }


}

