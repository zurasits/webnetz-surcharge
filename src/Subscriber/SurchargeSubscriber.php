<?php

namespace WebnetzSurcharge\Subscriber;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use WebnetzSurcharge\Service\DeliveryService;


/**
 * Handles surcharge logic for loaded products.
 */
class SurchargeSubscriber implements EventSubscriberInterface
{

    protected SystemConfigService $systemConfigService;
    private DeliveryService $deliveryService;

    /**
     * @param SystemConfigService $systemConfigService
     * @param DeliveryService $deliveryService
     */
    public function __construct(
        SystemConfigService $systemConfigService,
        DeliveryService $deliveryService
    )
    {
        $this->systemConfigService = $systemConfigService;
        $this->deliveryService = $deliveryService;
    }


    /**
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_LOADED_EVENT => 'onProductLoaded',
        ];
    }


    /**
     * Handles surcharge logic when products are loaded.
     *
     * @param EntityLoadedEvent $event The event triggered when entities are loaded.
     * @return void
     */
    public function onProductLoaded(EntityLoadedEvent $event): void
    {
        // Get surcharge configuration from system settings
        $category = $this->systemConfigService->get('WebnetzSurcharge.config.category');
        $surchargeType = $this->systemConfigService->get('WebnetzSurcharge.config.surchargeType');
        $surchargeAmount = $this->systemConfigService->get('WebnetzSurcharge.config.surchargeAmount');

        // Check if category or surchargeType is null, and abort if so
        if ($category === null || $surchargeType === null) {
            return;
        }

        // Handle surcharge for each product in the loaded entities
        /** @var ProductEntity $productEntity */
        foreach ($event->getEntities() as $productEntity) {
            $this->handleSurcharge($productEntity, $category, $surchargeType, $surchargeAmount, $event->getContext());
        }
    }


    /**
     * Handles the surcharge logic based on the product category and conditions.
     *
     * @param ProductEntity $productEntity The product entity to handle surcharge for.
     * @param string $category The category of the product ('freeShipping' or 'deliveryTime').
     * @param string $surchargeType The type of surcharge ('absolut' or 'percent').
     * @param float $surchargeAmount The amount of surcharge to apply.
     * @param Context $context The context for the operation.
     * @return void
     */
    private function handleSurcharge(ProductEntity $productEntity, string $category, string $surchargeType, float $surchargeAmount, Context $context): void
    {
        // Check if the product qualifies for free shipping and add surcharge if needed
        if ($category === 'freeShipping' && $productEntity->getShippingFree()) {
            $this->addSurchargePrice($productEntity, $surchargeType, $surchargeAmount);
        }

        // Check if the delivery time qualifies for surcharge and add it if needed
        if ($category === 'deliveryTime' && $this->deliveryService->isDeliveryTimeInstant($productEntity, $context)) {
            $this->addSurchargePrice($productEntity, $surchargeType, $surchargeAmount);
        }
    }


    /**
     * Adds a surcharge to the gross price of a product based on the specified surcharge type and amount.
     *
     * @param ProductEntity $productEntity The product to which the surcharge should be added.
     * @param string $surchargeType The type of surcharge ('absolut' for absolute amount, 'percent' for percentage).
     * @param float $surchargeAmount The amount of the surcharge.
     * @return bool Returns whether the surcharge was successfully added (true) or not (false).
     */
    private function addSurchargePrice(ProductEntity $productEntity, string $surchargeType, float $surchargeAmount): bool
    {
        // Tolerance value for floating point numbers
        $epsilon = 0.0001;

        // Check if the surcharge amount is close enough to zero
        if (abs($surchargeAmount) < $epsilon) {
            return false; // No surcharge required
        }

        // Get the current gross price of the product
        $priceGross = $productEntity->getPrice()->first();
        $currentGross = $priceGross->getGross();

        // Calculate the new gross price based on the surcharge type
        $newGross = match ($surchargeType) {
            'absolut' => $currentGross + $surchargeAmount,
            'percent' => $currentGross + ($currentGross * $surchargeAmount / 100),
            default => $currentGross,
        };

        // Set the new gross price
        $priceGross->setGross($newGross);

        // Surcharge successfully added
        return true;
    }

}