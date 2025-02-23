<?php

namespace Laravel\Cashier\Coupon;

use Laravel\Cashier\Exceptions\CouponException;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\Order\OrderItemCollection;
use Money\Money;

class PercentageDiscountHandler extends BaseCouponHandler
{
    /**
     * @param \Laravel\Cashier\Order\OrderItemCollection $items
     * @return \Laravel\Cashier\Order\OrderItemCollection
     */
    public function getDiscountOrderItems(OrderItemCollection $items)
    {
        if ($items->isEmpty()) {
            return new OrderItemCollection;
        }

        /** @var OrderItem $firstItem */
        $firstItem = $items->first();

        $unitPrice = $this->unitPrice($firstItem->getTotal());

        return $this->makeOrderItem([
            'process_at' => now(),
            'owner_type' => $firstItem->owner_type,
            'owner_id' => $firstItem->owner_id,
            'currency' => $unitPrice->getCurrency()->getCode(),
            'unit_price' => $unitPrice->getAmount(),
            'quantity' => $this->quantity($firstItem),
            'tax_percentage' => $this->taxPercentage($firstItem),
            'description' => $this->context('description'),
        ])->toCollection();
    }

    /**
     * @param \Money\Money $base The amount the discount is applied to.
     * @return \Money\Money
     */
    protected function unitPrice(Money $base)
    {
        $percentage = $this->context('percentage');

        if ($percentage < 1 || $percentage > 100) {
            throw new CouponException('Percentage has to be between 1 and 100');
        }

        $amount = $base->getAmount() / 100;

        // calculate new price
        $discount = mollie_array_to_money([
            'currency' => $base->getCurrency(),
            'value' => ($amount / 100) * $percentage,
        ]);

        return $discount->negative();
    }

    /**
     * @param \Laravel\Cashier\Order\OrderItem $firstItem
     * @return int
     */
    protected function quantity(OrderItem $firstItem)
    {
        $adaptive = $this->context('adaptive_quantity', false);

        return $adaptive ? $firstItem->quantity : 1;
    }

    /**
     * @param \Laravel\Cashier\Order\OrderItem $firstItem
     * @return float|int
     */
    protected function taxPercentage(OrderItem $firstItem)
    {
        $noTax = $this->context('no_tax', true);

        return $noTax ? 0 : $firstItem->getTaxPercentage();
    }
}
