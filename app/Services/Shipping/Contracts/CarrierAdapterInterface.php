<?php

namespace App\Services\Shipping\Contracts;

interface CarrierAdapterInterface
{
    /**
     * Get shipping rates
     *
     * @param array $shipment Shipment details including weight, dimensions, pincodes, etc.
     * @return array Array of available services with rates
     */
    public function getRates(array $shipment): array;

    /**
     * Create a shipment
     *
     * @param array $data Shipment creation data
     * @return array Created shipment details including tracking number
     */
    public function createShipment(array $data): array;

    /**
     * Cancel a shipment
     *
     * @param string $trackingNumber
     * @return bool Success status
     */
    public function cancelShipment(string $trackingNumber): bool;

    /**
     * Track a shipment
     *
     * @param string $trackingNumber
     * @return array Tracking information including status and events
     */
    public function trackShipment(string $trackingNumber): array;

    /**
     * Check serviceability for pincode
     *
     * @param string $pickupPincode
     * @param string $deliveryPincode
     * @param string $paymentMode prepaid or cod
     * @return bool
     */
    public function checkServiceability(string $pickupPincode, string $deliveryPincode, string $paymentMode): bool;

    /**
     * Schedule a pickup
     *
     * @param array $pickup Pickup details
     * @return array Pickup confirmation details
     */
    public function schedulePickup(array $pickup): array;

    /**
     * Get rate async for parallel processing
     *
     * @param array $shipment
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function getRateAsync(array $shipment): \GuzzleHttp\Promise\PromiseInterface;

    /**
     * Print shipping label
     *
     * @param string $trackingNumber
     * @return string Label URL or content
     */
    public function printLabel(string $trackingNumber): string;
}